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
        foreach (['Folders', 'Galleries'] as $tableName) {
            $this->{$tableName} = $this->fetchTable($tableName); // @phpstan-ignore-line
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int|null
    {
        $this->io = $io;
        $this->args = $args;
        $this->sourceConnection = ConnectionManager::get((string)$args->getOption('source-connection'));
        if (!$this->sourceConnection instanceof Connection) {
            throw new UnexpectedValueException(
                sprintf(
                    'Invalid connection type: expected "%s", got "%s"',
                    Connection::class,
                    get_debug_type($this->sourceConnection),
                )
            );
        }

        $result = $this->Folders->getConnection()->transactional(function (): int {
            $galleries = array_map(
                fn (array $gallery): ObjectEntity|bool => $this->importGallery($gallery),
                $this->getGalleries(),
            );
            $successfulGalleries = array_filter($galleries);
            $this->io->info(sprintf('Imported %d galleries out of %d', count($successfulGalleries), count($galleries)));

            return static::CODE_SUCCESS;
        });

        return (int)$result;
    }

    /**
     * Import a gallery and its related objects.
     *
     * @param Be3Gallery $data Gallery data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if the import failed (see log messages)
     */
    protected function importGallery(array $data): ObjectEntity|false
    {
        $gallery = $this->findOrCreateGallery($data['original_uname'], $data);
        if ($gallery === false) {
            return false;
        }

        if (empty($gallery->get('gallery_contains'))) {
            $sourceAttaches = $this->getBe3RelatedMedia($data['original_id'], 'attached_to', (string)$this->args->getOption('download-baseurl'));
            if (!empty($sourceAttaches)) {
                $attaches = array_map(
                    fn (array $object): Media|bool => $this->findOrCreateMedia(
                        $object['original_uname'],
                        $object,
                        $object['type_name'],
                        !empty($object['stream_hash_md5']) && $this->args->getOption('download'),
                    ),
                    $sourceAttaches,
                );
                $successfulAttaches = array_filter($attaches);
                if (count($attaches) !== count($successfulAttaches)) {
                    $this->io->warning(sprintf('One or more attaches of gallery "%s" had an error, skipping import of the gallery', $data['original_uname']));

                    return false;
                }

                $action = new AddRelatedObjectsAction(['association' => $this->Galleries->getAssociation('GalleryContains')]);
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
                    new FunctionExpression('COALESCE', [
                        'obp.property' => 'identifier',
                        new FunctionExpression('JSON_OBJECT'),
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
                    'table' => $this->sourceConnection->newQuery()
                        ->select([
                            'id' => 'obp.object_id',
                            'property' => new FunctionExpression('JSON_OBJECTAGG', [
                                'p.name' => 'identifier',
                                'obp.property_value' => 'identifier',
                            ]),
                        ])
                        ->from(['p' => 'properties'])
                        ->innerJoin(['obp' => 'object_properties'], 'obp.property_id = p.id')
                        ->group('obp.object_id'),
                    'conditions' => 'obp.id = o.id',
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
     * Get related media from a BEdita 3 database.
     *
     * This method is specifically for relations with media objects, and returns more information about the related objects
     * than the {@see \App\Command\ImportBe3Trait::getSourceRelatedObjects()} method.
     *
     * @param int $objectId Object ID
     * @param string $relation Relation name
     * @param string|null $providerUrlPrefix Prefix to prepend to the provider URL when it's a relative URL
     * @return array<Be3Media>
     */
    protected function getBe3RelatedMedia(int $objectId, string $relation, string|null $providerUrlPrefix = null): array
    {
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
                'relation_params' => 'obr.params',
                'image_width' => 'i.width',
                'image_height' => 'i.height',
                'provider' => 'v.provider',
                'provider_uid' => 'v.video_uid',
                'provider_thumbnail' => 'v.thumbnail',
                'provider_url' => $providerUrlPrefix === null
                    ? 's.uri'
                    : new FunctionExpression('IF', [
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
                'name' => new FunctionExpression('COALESCE', [
                    's.original_name' => 'identifier',
                    's.name' => 'identifier',
                ]),
                'extra' => new FunctionExpression('JSON_MERGE_PATCH', [
                    new FunctionExpression('COALESCE', [
                        'obp.properties' => 'identifier',
                        new FunctionExpression('JSON_OBJECT'),
                    ]),
                    new FunctionExpression('JSON_OBJECT', [
                        'rights', 'o.rights' => 'identifier',
                        'license', 'o.license' => 'identifier',
                        'creator', 'o.creator' => 'identifier',
                        'publisher', 'o.publisher' => 'identifier',
                        'note', 'o.note' => 'identifier',
                    ]),
                ]),
                'stream_mime_type' => 's.mime_type',
                'stream_file_size' => 's.file_size',
                'stream_hash_md5' => 's.hash_file',
            ])
            ->from(['o' => 'objects'])
            ->join([
                'obr' => [
                    'table' => 'object_relations',
                    'conditions' => [
                        'obr.object_id' => $objectId,
                        'obr.switch' => $relation,
                        'obr.id = o.id',
                    ],
                ],
                'ot' => [
                    'table' => 'object_types',
                    'conditions' => [
                        'ot.id = o.object_type_id',
                        'ot.module_name' => 'multimedia',
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
                    'table' => $this->sourceConnection->newQuery()
                        ->select([
                            'id' => 'op.object_id',
                            'properties' => new FunctionExpression('JSON_OBJECTAGG', [
                                'p.name' => 'identifier',
                                'op.property_value' => 'identifier',
                            ]),
                        ])
                        ->from(['op' => 'object_properties'])
                        ->innerJoin(['p' => 'properties'], 'p.id = op.property_id')
                        ->group('op.object_id'),
                    'conditions' => 'obp.id = o.id',
                ],
            ])
            ->orderAsc('obr.priority')
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($media === false) {
            throw new RuntimeException(sprintf('Error retrieving "%s" objects related to object %d', $relation, $objectId));
        }

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
            ->contain(['GalleryContains'])
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
        $gallery = $this->Galleries->patchEntity($gallery, array_merge($data, [
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
