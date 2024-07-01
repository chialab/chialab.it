<?php
declare(strict_types=1);

namespace App\Command;

use BEdita\Core\Model\Action\AddRelatedObjectsAction;
use BEdita\Core\Model\Entity\Media;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Table\FoldersTable;
use BEdita\Core\Model\Table\ObjectsTable;
use BEdita\Core\Model\Table\UsersTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Expression\FunctionExpression;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Hash;
use PDO;
use RuntimeException;
use UnexpectedValueException;

/**
 * Import old website.
 *
 * @phpstan-type Be3Gallery array{status: 'on'|'draft'|'off', original_id: int, original_uname: string, title: string, description: string, created: string, modified: string, extra: string}
 * @phpstan-import-type Be3Media from \App\Command\ImportBe3Trait
 */
class ImportOld extends Command
{
    use ImportBe3Trait;

    protected ConnectionInterface $sourceConnection;
    protected ConsoleIo $io;
    protected Arguments $args;

    protected FoldersTable $Folders;
    protected ObjectsTable $Galleries;

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription([
                'Import "Aule" entities',
                'This command assumes that the folder tree has already been created in the destination database.',
            ])
            ->addOption('source-connection', [
                'short' => 'C',
                'help' => 'Name of connection to use for source database.',
                'choices' => ConnectionManager::configured(),
                'default' => 'old-import',
            ])
            ->addOption('download', [
                'short' => 'd',
                'help' => 'Download files and create streams.',
                'boolean' => true,
            ])
            ->addOption('download-baseurl', [
                'help' => 'Base URL where to download files from',
                'default' => 'https://static.chialab.it',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        foreach (['Folders', 'Galleries', 'Streams'] as $tableName) {
            $this->{$tableName} = $this->fetchTable($tableName); // @phpstan-ignore-line
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception Transaction error
     */
    public function execute(Arguments $args, ConsoleIo $io): int|null
    {
        $this->io = $io;
        $this->args = $args;
        $sourceConnection = ConnectionManager::get((string)$args->getOption('source-connection'));
        if (!$sourceConnection instanceof Connection) {
            throw new UnexpectedValueException(
                sprintf(
                    'Invalid connection type: expected "%s", got "%s"',
                    Connection::class,
                    get_debug_type($sourceConnection),
                )
            );
        }

        $this->sourceConnection = $sourceConnection;
        $i = 0;
        foreach ($this->mediaIterator() as $data) {
            $result = $this->Folders->getConnection()->transactional(
                fn (): Media|bool => $this->findOrCreateMedia(
                    $data['original_uname'],
                    $data,
                    $data['type_name'],
                    !empty($data['stream_hash_md5']) && $this->args->getOption('download'),
                ),
            );
            if ($result === false) {
                $this->io->error(sprintf('Error importing media "%s"', $data['original_uname']));
                continue;
            }

            $i++;
            $this->io->verbose(sprintf('(%d) Imported media "%s"', $i, $data['original_uname']));
            if ($i % 100 === 0) {
                $this->io->info(sprintf('Imported %d media', $i));
            }
        }

        $galleries = array_map(
            fn (array $gallery): ObjectEntity|bool => $this->importGallery($gallery),
            $this->getGalleries(),
        );
        $successfulGalleries = array_filter($galleries);
        $this->io->info(sprintf('Imported %d galleries out of %d', count($successfulGalleries), count($galleries)));

        return static::CODE_SUCCESS;
    }

    /**
     * Import a gallery and its related objects.
     *
     * @param Be3Gallery $data Gallery data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if the import failed (see log messages)
     * @throws \Exception Transaction error
     */
    protected function importGallery(array $data): ObjectEntity|false
    {
        $gallery = $this->findOrCreateGallery($data['original_uname'], $data);
        if ($gallery === false) {
            return false;
        }

        if (empty($gallery->get('has_media'))) {
            $sourceAttaches = $this->getBe3RelatedMedia($data['original_id'], 'attached_to', (string)$this->args->getOption('download-baseurl'));
            if (!empty($sourceAttaches)) {
                $attaches = array_map(
                    fn (array $object): Media|bool => $this->Folders->getConnection()->transactional(
                        fn (): Media|bool => $this->findOrCreateMedia(
                            $object['original_uname'],
                            $object,
                            $object['type_name'],
                            !empty($object['stream_hash_md5']) && $this->args->getOption('download'),
                        ),
                    ),
                    $sourceAttaches,
                );
                $successfulAttaches = array_filter($attaches);
                if (count($attaches) !== count($successfulAttaches)) {
                    $this->io->warning(sprintf('One or more attaches of gallery "%s" had an error, skipping import of the gallery', $data['original_uname']));

                    return false;
                }

                $action = new AddRelatedObjectsAction(['association' => $this->Galleries->getAssociation('HasMedia')]);
                $action(['entity' => $gallery, 'relatedEntities' => $successfulAttaches]);
            }
        }

        return $gallery;
    }

    /**
     * Get gallery objects.
     *
     * @return array<Be3Gallery>
     */
    protected function getGalleries(): array
    {
        $galleries = $this->sourceConnection->newQuery()
            ->select([
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'status' => 'o.status',
                'title' => 'o.title',
                'description' => 'o.description',
                'created' => 'o.created',
                'modified' => 'o.modified',
                'extra' => new FunctionExpression('JSON_MERGE_PATCH', [
                    // MySQL throws if `p.name` is null (object has no properties)
                    // We handle it by IFNULLing to a specific property name, which we then remove from the JSON object
                    new FunctionExpression('JSON_REMOVE', [
                        new FunctionExpression('JSON_OBJECTAGG', [
                            new FunctionExpression('IFNULL', [
                                'p.name' => 'identifier',
                                'no__properties__',
                            ]),
                            'obp.property_value' => 'identifier',
                        ]),
                        '$.no__properties__',
                    ]),
                    new FunctionExpression('JSON_OBJECT', [
                        'rights', 'o.rights' => 'identifier',
                        'license', 'o.license' => 'identifier',
                        'creator', 'o.creator' => 'identifier',
                        'publisher', 'o.publisher' => 'identifier',
                        'note', 'o.note' => 'identifier',
                    ]),
                ]),
            ])
            ->from(['o' => 'objects'])
            ->join([
                'ot' => [
                    'table' => 'object_types',
                    'conditions' => [
                        'ot.id = o.object_type_id',
                        'ot.name' => 'gallery',
                    ],
                ],
                'obp' => [
                    'type' => 'LEFT',
                    'table' => 'object_properties',
                    'conditions' => 'obp.object_id = o.id',
                ],
                'p' => [
                    'type' => 'LEFT',
                    'table' => 'properties',
                    'conditions' => 'p.id = obp.property_id',
                ],
            ])
            ->group('o.id')
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($galleries === false) {
            throw new RuntimeException('Error retrieving galleries');
        }

        return $galleries;
    }

    /**
     * Get media as iterable.
     *
     * @return iterable<Be3Media>
     */
    protected function mediaIterator(): iterable
    {
        $lastId = 0;
        while (true) {
            $media = $this->getMedia($lastId);
            if (empty($media)) {
                break;
            }

            $lastId = $media[count($media) - 1]['original_id'];
            yield from $media;
        }
    }

    /**
     * Paginate through all media.
     *
     * @param int $fromId Media ID to start from
     * @param int $limit Amount of media objects to fetch
     * @return array<Be3Media>
     */
    protected function getMedia(int $fromId = 0, int $limit = 100): array
    {
        $providerUrlPrefix = (string)$this->args->getOption('download-baseurl');
        $query = $this->sourceConnection->newQuery();
        $media = $query
            ->select([
                'type_name' => 'ot.name',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'title' => 'o.title',
                'description' => 'o.description',
                'body' => 'c.body',
                'status' => 'o.status',
                'created' => 'o.created',
                'modified' => 'o.modified',
                'image_width' => 'i.width',
                'image_height' => 'i.height',
                'provider' => 'v.provider',
                'provider_uid' => 'v.video_uid',
                'provider_thumbnail' => 'v.thumbnail',
                'provider_url' => new FunctionExpression('IF', [
                    's.uri IS NULL' => 'literal',
                    'null' => 'literal',
                    new FunctionExpression('IF', [
                        'LEFT(s.uri, 4) = "http"' => 'literal',
                        's.uri' => 'literal',
                        new FunctionExpression('CONCAT', [
                            rtrim($providerUrlPrefix, '/'),
                            '/',
                            "TRIM(LEADING '/' FROM s.uri)" => 'literal',
                        ]),
                    ]),
                ]),
                'provider_extra' => new FunctionExpression('IF', [
                    'c.duration IS NULL' => 'literal',
                    'null' => 'literal',
                    new FunctionExpression('JSON_OBJECT', [
                        'duration', 'c.duration' => 'literal',
                    ]),
                ]),
                'name' => new FunctionExpression('COALESCE', ['s.original_name' => 'identifier', 's.name' => 'identifier']),
                'stream_mime_type' => 's.mime_type',
                'stream_file_size' => 's.file_size',
                'stream_hash_md5' => 's.hash_file',
                'extra' => new FunctionExpression('JSON_MERGE_PATCH', [
                    // MySQL throws if `p.name` is null (object has no properties)
                    // We handle it by IFNULLing to a specific property name, which we then remove from the JSON object
                    new FunctionExpression('JSON_REMOVE', [
                        new FunctionExpression('JSON_OBJECTAGG', [
                            new FunctionExpression('IFNULL', [
                                'p.name' => 'identifier',
                                'no__properties__',
                            ]),
                            'obp.property_value' => 'identifier',
                        ]),
                        '$.no__properties__',
                    ]),
                    new FunctionExpression('JSON_OBJECT', [
                        'rights', 'o.rights' => 'identifier',
                        'license', 'o.license' => 'identifier',
                        'creator', 'o.creator' => 'identifier',
                        'publisher', 'o.publisher' => 'identifier',
                        'note', 'o.note' => 'identifier',
                    ]),
                ]),
            ])
            ->from(['o' => 'objects'])
            ->join([
                'ot' => [
                    'table' => 'object_types',
                    'conditions' => [
                        'ot.id = o.object_type_id',
                        'ot.name IN' => ['b_e_file', 'image', 'audio', 'video', 'caption'], // there is only one "application" and it's a Flash file :')
                    ],
                ],
                's' => [
                    'table' => 'streams',
                    'type' => 'LEFT',
                    'conditions' => 's.id = o.id',
                ],
                'i' => [
                    'table' => 'images',
                    'type' => 'LEFT',
                    'conditions' => 'i.id = o.id',
                ],
                'v' => [
                    'table' => 'videos',
                    'type' => 'LEFT',
                    'conditions' => 'v.id = o.id',
                ],
                'c' => [
                    'table' => 'contents',
                    'type' => 'LEFT',
                    'conditions' => 'c.id = o.id',
                ],
                'obp' => [
                    'type' => 'LEFT',
                    'table' => 'object_properties',
                    'conditions' => 'obp.object_id = o.id',
                ],
                'p' => [
                    'type' => 'LEFT',
                    'table' => 'properties',
                    'conditions' => 'p.id = obp.property_id',
                ],
            ])
            ->where(['o.id >' => $fromId])
            ->group(['o.id'])
            ->limit($limit)
            ->orderAsc('o.id')
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($media === false) {
            throw new RuntimeException('Error retrieving media');
        }

        /** @var array<Be3Media> $media */
        $media = array_map(
            fn (array $object): array => $object + ['relation_params' => null],
            $media,
        );

        return $media;
    }

    /**
     * Find the imported gallery, or create a new one.
     *
     * @param string $uname Gallery uname
     * @param Be3Gallery $data Gallery data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateGallery(string $uname, array $data): ObjectEntity|false
    {
        /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $gallery */
        $gallery = $this->Galleries->find()
            ->where(compact('uname'))
            ->contain(['HasMedia'])
            ->first();
        if ($gallery !== null) {
            if (Hash::get((array)$gallery->extra, 'imported.uname') === $data['original_uname'] && Hash::get((array)$gallery->extra, 'imported.id') === $data['original_id'] && $gallery->type === 'galleries') {
                $this->io->verbose(sprintf('Reusing already imported gallery "%s"', $uname));

                return $gallery;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping gallery import', $uname));

            return false;
        }

        $gallery = $this->Galleries->newEntity(compact('uname'));
        $gallery->created_by = UsersTable::ADMIN_USER;
        $gallery->modified_by = UsersTable::ADMIN_USER;
        $gallery = $this->Galleries->patchEntity($gallery, array_merge_recursive($data, [
            'extra' => [
                'imported' => [
                    'id' => $data['original_id'],
                    'uname' => $data['original_uname'],
                ],
            ],
        ]));
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $gallery */
        $gallery = $this->Galleries->saveOrFail($gallery, ['atomic' => false]);

        return $gallery;
    }
}
