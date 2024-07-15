<?php
declare(strict_types=1);

namespace App\Command;

use BEdita\Core\Model\Action\AddRelatedObjectsAction;
use BEdita\Core\Model\Entity\Folder;
use BEdita\Core\Model\Entity\Media;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Table\CategoriesTable;
use BEdita\Core\Model\Table\DateRangesTable;
use BEdita\Core\Model\Table\FoldersTable;
use BEdita\Core\Model\Table\LinksTable;
use BEdita\Core\Model\Table\LocationsTable;
use BEdita\Core\Model\Table\ObjectsTable;
use BEdita\Core\Model\Table\ProfilesTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Expression\FunctionExpression;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Exception;
use PDO;
use UnexpectedValueException;

/**
 * Import old website.
 *
 * @phpstan-import-type Be3Content from \App\Command\ImportBe3Trait
 * @phpstan-import-type Be3Media from \App\Command\ImportBe3Trait
 * @phpstan-import-type Be3Folder from \App\Command\ImportBe3Trait
 * @phpstan-import-type Be3Gallery from \App\Command\ImportBe3Trait
 */
class ImportOld extends Command
{
    use ImportBe3Trait;

    protected ConnectionInterface $sourceConnection;
    protected ConsoleIo $io;
    protected Arguments $args;

    protected ObjectsTable $Objects;
    protected FoldersTable $Folders;
    protected CategoriesTable $Categories;
    protected ObjectsTable $Documents;
    protected ObjectsTable $Galleries;
    protected LinksTable $Links;
    protected ProfilesTable $Profiles;
    protected ObjectsTable $Events;
    protected LocationsTable $Locations;
    protected DateRangesTable $DateRanges;

    protected const PLACEHOLDER_REGEX = '#<a class="(?:placeholder|placeref)" href="([^"]+)">(.*?)</a>#i';
    protected const PLACEHOLDER_REPLACE_FORMAT = '<div data-placeholder="%d"><!--BE-PLACEHOLDER.%d.%s--></div>';

    protected const ROOT_FOLDERS_MAP = [
        'chialab-3' => 'chialab-design-company',
        'chialab' => 'chialab-2012',
        'chialab2017' => 'chialab-2017',
        'chialab-open-source' => 'chialab-open-source',
        'didascalicon' => 'didascalicon',
        'illustratori' => 'illustratori',
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription([
                'Import old website entities',
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
        foreach (['Folders', 'Objects', 'Galleries', 'Streams', 'Links', 'Profiles', 'Documents', 'Categories', 'Events', 'Locations', 'DateRanges'] as $tableName) {
            $this->{$tableName} = $this->fetchTable($tableName); // @phpstan-ignore-line
            // Disable Timestamp behavior to let us set `created` and `modified`
            if ($this->{$tableName}->hasBehavior('Timestamp')) {
                $this->{$tableName}->removeBehavior('Timestamp');
            }
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
        foreach (static::ROOT_FOLDERS_MAP as $sourceUname => $destinationUname) {
            $this->importFolder($sourceUname, $destinationUname);
        }

        $relationsMap = [
            'see_also' => ['artifacts', 'seealso'],
            'featured' => ['featured_content'],
        ];
        foreach ($relationsMap as $destinationRelation => $sourceRelations) {
            $this->io->info(sprintf('Importing relations "%s" as relation "%s"', implode(', ', $sourceRelations), $destinationRelation));
            $relationIds = $this->sourceConnection->newQuery()
                ->select(['id', 'object_id'])
                ->from(['obr' => 'object_relations'])
                ->where(['obr.switch IN' => $sourceRelations])
                ->order(['obr.id', 'obr.priority'])
                ->execute()
                ->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
            if ($relationIds === false) {
                $this->io->error(sprintf('Error fetching object IDs for relation "%s"', implode(', ', $sourceRelations)));

                continue;
            }

            foreach ($relationIds as $leftId => $rightIds) {
                $this->addRelation($leftId, $rightIds, $destinationRelation);
            }
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Add relation between objects.
     *
     * @param int $leftId Left object ID
     * @param array $rightIds Right object IDs
     * @param string $relation Relation name
     * @return bool
     */
    protected function addRelation(int $leftId, array $rightIds, string $relation): bool
    {
        /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $leftObject */
        $leftObject = $this->Objects->find()
            ->where(new FunctionExpression('JSON_CONTAINS', [
                'extra' => 'identifier',
                $leftId,
                '$.imported.id',
            ]))
            ->first();
        if ($leftObject === null) {
            $this->io->error(sprintf('Error finding left object for imported ID %d', $leftId));

            return false;
        }

        /** @var array<\BEdita\Core\Model\Entity\ObjectEntity> $rightObjects */
        $rightObjects = $this->Objects->find()
            ->where(['extra->\'$.imported.id\' IN' => $rightIds], ['extra->\'$.imported.id\'' => 'integer'])
            ->all()
            ->toArray();
        if (empty($rightObjects)) {
            $this->io->error(sprintf('No right objects found for imported IDs %s related to imported ID %d', implode(', ', $rightIds), $leftId));

            return false;
        }

        $action = new AddRelatedObjectsAction(['association' => $leftObject->getTable()->getAssociation(Inflector::camelize($relation))]);
        $action(['entity' => $leftObject, 'relatedEntities' => $rightObjects]);
        $this->io->verbose(sprintf('Added relation "%s" between object %d and objects %s', $relation, $leftObject->id, implode(', ', Hash::extract($rightObjects, '{*}.id'))));

        return true;
    }

    /**
     * Import a folder and its contents.
     *
     * @param string $sourceUname Source folder uname
     * @param string|null $destinationUname Optional destination folder uname, defaults to source folder uname
     * @return \BEdita\Core\Model\Entity\Folder|false
     * @throws \Exception Import error
     */
    protected function importFolder(string $sourceUname, string|null $destinationUname = null): Folder|false
    {
        if ($destinationUname === null) {
            $destinationUname = $sourceUname;
        }

        $this->io->info(sprintf('Importing folder "%s" to "%s"', $sourceUname, $destinationUname));
        $sourceFolder = $this->getBe3Folder($sourceUname);
        if ($sourceFolder === false) {
            $this->io->warning(sprintf('Folder "%s" not found in source database, skipping import', $sourceUname));

            return false;
        }

        $destinationFolder = $this->findOrCreateFolder($destinationUname, $sourceFolder);
        $imported = [];
        $connection = $this->Folders->getConnection();
        $contents = $this->getBe3FolderContents($sourceFolder['original_id'], $sourceFolder['content_order']);
        foreach ($contents as $content) {
            try {
                /** @var \BEdita\Core\Model\Entity\ObjectEntity|int|false $entity */
                $entity = match ($content['type_name']) {
                    'section' => $this->importFolder($content['original_uname']),
                    'document',
                    'short_news',
                    'git_project',
                    'portfolio_item' => $connection->transactional(fn (): ObjectEntity|bool => $this->importDocument($content)),
                    default => $connection->transactional(fn (): ObjectEntity|bool => $this->findOrCreateObject($content['original_uname'], $content)),
                };

                if (!($entity instanceof ObjectEntity)) {
                    continue;
                }

                $imported[] = $entity;
                $this->io->verbose(sprintf('Imported content "%s"', $entity->uname));
            } catch (Exception $e) {
                $this->io->error(sprintf('Error importing object "%s" from folder "%s" to folder "%s": %s', $content['original_uname'], $sourceUname, $destinationUname, $e->getMessage()));
                throw $e;
            }
        }

        $media = $this->getBe3FolderMedia($sourceFolder['original_id'], $sourceFolder['content_order'], (string)$this->args->getOption('download-baseurl'));
        foreach ($media as $content) {
            /** @var \BEdita\Core\Model\Entity\Media|false $entity */
            $entity = $connection->transactional(fn (): Media|bool => $this->findOrCreateObject($content['original_uname'], $content));
            if ($entity === false) {
                continue;
            }

            $imported[] = $entity;
            $this->io->verbose(sprintf('Imported media "%s"', $entity->uname));
        }

        $alreadyImported = Hash::extract((array)$destinationFolder->children, '{*}.uname');
        $missingImported = array_filter(
            $imported,
            fn (ObjectEntity $entity): bool => !in_array($entity->uname, $alreadyImported, true),
        );
        if (!empty($missingImported)) {
            /** @var \BEdita\Core\Model\Entity\ObjectEntity $child */
            foreach ($missingImported as $child) {
                $child->parents = array_merge($child->parents ?? [], [$destinationFolder]);
                $child->getTable()->saveOrFail($child);
            }
        }

        return $destinationFolder;
    }

    /**
     * Import a content as document, along with its related objects.
     *
     * @param Be3Content $data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if an error occurred importing a related object
     */
    protected function importDocument(array $data): ObjectEntity|false
    {
        // Fix datetime custom props: JSONAPI requires the 'T' between date and time
        foreach (['start_date', 'end_date'] as $prop) {
            if (empty($data[$prop])) {
                continue;
            }

            $data[$prop] = str_replace(' ', 'T', $data[$prop]);
        }

        $document = $this->findOrCreateDocument($data['original_uname'], $data, [
            'Categories',
            'Parents',
            'Poster',
            'Placeholder',
            'HasLinks',
            'HasMedia',
            'HasClients',
            'Team',
            'SeeAlso',
        ]);
        if ($document === false) {
            return false;
        }

        if (!empty($document->get('categories'))) {
            $sourceCategories = array_map(
                fn (array $category): string => $category['label'],
                $this->getBe3ObjectCategories($data['original_id'], [], $data['type_name']),
            );
            if (!empty($sourceCategories)) {
                $categories = $this->getCategoriesByLabel($sourceCategories, [], 'documents');
                $action = new AddRelatedObjectsAction(['association' => $this->Documents->getAssociation('Categories')]);
                $action(['entity' => $document, 'relatedEntities' => $categories]);
            }
        }

        $document = $this->importRelation('poster_of', 'poster', $data['original_id'], $document, true);
        if ($document === false) {
            $this->io->warning(sprintf('One or more posters of document "%s" had an error, skipping import of the document', $data['original_uname']));

            return false;
        }

        $document = $this->importRelation('link', 'has_links', $data['original_id'], $document);
        if ($document === false) {
            $this->io->warning(sprintf('One or more links of document "%s" had an error, skipping import of the document', $data['original_uname']));

            return false;
        }

        $document = $this->importRelation('customer_of', 'has_clients', $data['original_id'], $document);
        if ($document === false) {
            $this->io->warning(sprintf('One or more clients of document "%s" had an error, skipping import of the document', $data['original_uname']));

            return false;
        }

        $document = $this->importRelation('working_on', 'team', $data['original_id'], $document);
        if ($document === false) {
            $this->io->warning(sprintf('One or more team member of document "%s" had an error, skipping import of the document', $data['original_uname']));

            return false;
        }

        $document = $this->importAttaches($data, $document);
        if ($document === false) {
            return false;
        }

        if (empty($document->get('has_location')) && (!empty($data['address']) || !empty($data['coords']))) {
            $location = $this->findOrCreateLocation(
                $data['location_title'] ?? sprintf('location-%s', $data['original_uname']),
                array_merge($data, ['title' => $data['location_title']]),
            );
            if ($location === false) {
                $this->io->warning(sprintf('Error importing location of document "%s", skipping import of the document', $data['original_uname']));

                return false;
            }

            $action = new AddRelatedObjectsAction(['association' => $this->Documents->getAssociation('HasLocation')]);
            $action(['entity' => $document, 'relatedEntities' => [$location]]);
        }

        if ($document->isDirty()) {
            /** @var \BEdita\Core\Model\Entity\ObjectEntity $document */
            $document = $this->Documents->saveOrFail($document, ['atomic' => false]);
        }

        return $document;
    }

    /**
     * Import related entities.
     *
     * Do NOT use for attaches/placeholders! Use {@see ImportOld::importAttaches()}.
     *
     * @see \App\Command\ImportOld::importAttaches()
     * @param string $sourceRelation Relation name in source database (camel_case)
     * @param string $destinationRelation Relation name in destination database (camel_case)
     * @param int $sourceId Source entity ID
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Entity to which relate entities to
     * @param bool $media Whether the relation is with media entities
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if any import failed
     */
    protected function importRelation(string $sourceRelation, string $destinationRelation, int $sourceId, ObjectEntity $entity, bool $media = false): ObjectEntity|false
    {
        if (empty($entity->get($destinationRelation))) {
            $sourceRelated = $media
                ? $this->getBe3RelatedMedia($sourceId, $sourceRelation, (string)$this->args->getOption('download-baseurl'))
                : $this->getBe3RelatedObjects($sourceId, $sourceRelation);
            if (!empty($sourceRelated)) {
                $related = array_map(
                    fn (array $object): ObjectEntity|bool => $this->findOrCreateObject($object['original_uname'], $object),
                    $sourceRelated,
                );
                $relatedEntities = array_filter(
                    $related,
                    fn (ObjectEntity|bool|int $object): bool => $object instanceof ObjectEntity,
                );
                if (count($related) !== count($relatedEntities)) {
                    return false;
                }

                $association = $entity->getTable()->getAssociation(Inflector::camelize($destinationRelation));
                $action = new AddRelatedObjectsAction(compact('association'));
                $action(compact('entity', 'relatedEntities'));
            }
        }

        return $entity;
    }

    /**
     * Import attaches and placeholders.
     *
     * @param Be3Content $data Source entity data
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Entity to which relate attaches and placeholders
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if any import failed (see log messages)
     */
    protected function importAttaches(array $data, ObjectEntity $entity): ObjectEntity|false
    {
        // Attaches can be either `placeholder` or `attach`, depending on if the document's body contains a reference to the media
        // Placeholders can also be `gallery` objects, so we search and append those too
        $sourceAttaches = collection($this->getBe3RelatedMedia($data['original_id'], 'attached_to', (string)$this->args->getOption('download-baseurl')))
            ->append($this->getBe3RelatedObjects($data['original_id'], 'attached_to'));
        if (!$sourceAttaches->isEmpty()) {
            $placeholders = [];
            $skippedPlaceholders = false; // media skipped because of an error
            $entity->body = (string)preg_replace_callback(
                static::PLACEHOLDER_REGEX,
                function (array $match) use ($data, $sourceAttaches, &$placeholders, &$skippedPlaceholders): string {
                    $placeholders[] = $uname = $match[1];
                    /** @var Be3Media|Be3Content|null $sourceAttach */
                    $sourceAttach = $sourceAttaches->firstMatch(['original_uname' => $uname]);
                    if (empty($sourceAttach)) {
                        $this->io->error(sprintf('Placeholder media "%s" not found in attaches of entity "%s"', $uname, $data['original_uname']));
                        $skippedPlaceholders = true;

                        return $match[0];
                    }

                    $placeholder = $this->findOrCreateObject($uname, $sourceAttach);
                    if ($placeholder === false) {
                        $skippedPlaceholders = true;

                        return $match[0];
                    }

                    return sprintf(
                        static::PLACEHOLDER_REPLACE_FORMAT,
                        $placeholder->id,
                        $placeholder->id,
                        base64_encode($sourceAttach['relation_params'] ?: 'undefined'),
                    );
                },
                $data['body'],
            );
            if ($skippedPlaceholders) {
                $this->io->warning(sprintf('One or more placeholders of document "%s" had an error, skipping import of the document', $data['original_uname']));

                return false;
            }

            /** @var array<Be3Media> $toBeAttaches */
            $toBeAttaches = $sourceAttaches
                ->filter(fn (array $attach): bool => !in_array($attach['original_uname'], $placeholders, true))
                ->toArray();
            $attaches = array_map(
                fn (array $data): ObjectEntity|bool => $this->findOrCreateObject($data['original_uname'], $data),
                $toBeAttaches,
            );
            /** @var array<\BEdita\Core\Model\Entity\ObjectEntity> $successfulAttaches */
            $successfulAttaches = array_filter($attaches);
            if (count($attaches) !== count($successfulAttaches)) {
                $this->io->warning(sprintf('One or more attaches of document "%s" had an error, skipping import of the document', $data['original_uname']));

                return false;
            }

            // Add only missing "attach"
            $currentAttachUnames = array_map(
                fn (ObjectEntity $attach): string => $attach->uname,
                $entity->get('attach') ?? [],
            );
            $missingAttaches = array_filter(
                $successfulAttaches,
                fn (ObjectEntity $attach): bool => !in_array($attach->uname, $currentAttachUnames, true),
            );
            if (!empty($missingAttaches)) {
                $action = new AddRelatedObjectsAction(['association' => $this->Documents->getAssociation('HasMedia')]);
                $action(['entity' => $entity, 'relatedEntities' => $missingAttaches]);
            }
        }

        return $entity;
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

        $gallery = $this->importRelation('attached_to', 'has_media', $data['original_id'], $gallery, true);
        if ($gallery === false) {
            $this->io->warning(sprintf('One or more attaches of gallery "%s" had an error, skipping import of the gallery', $data['original_uname']));

            return false;
        }

        return $gallery;
    }

    /**
     * Import an event and its related links.
     *
     * @param Be3Content $data Event data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if the import failed (see log messages)
     */
    protected function importEvent(array $data): ObjectEntity|false
    {
        $event = $this->findOrCreateEvent($data['original_uname'], $data);
        if ($event === false) {
            return false;
        }

        $event = $this->importRelation('link', 'has_links', $data['original_id'], $event);
        if ($event === false) {
            $this->io->warning(sprintf('One or more links of event "%s" had an error, skipping import of the event', $data['original_uname']));

            return false;
        }

        return $event;
    }

    /**
     * Find or create an object of any type, excluded documents which need to be imported.
     *
     * @see \App\Command\ImportOld::importDocument()
     * @param string $uname Uname of the object to be imported
     * @param Be3Content|Be3Media|Be3Gallery $data Data of the object to be imported
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateObject(string $uname, array $data): ObjectEntity|false
    {
        $entity = match ($data['type_name']) {
            'gallery' => $this->importGallery($data),
            'card' => $this->findOrCreateAuthor($uname, $data),
            'link' => $this->findOrCreateLink($uname, $data),
            'event' => $this->importEvent($data),
            'audio',
            'b_e_file',
            'image',
            'video' => $this->findOrCreateMedia(
                $uname,
                $data,
                $data['type_name'],
                !empty($data['stream_hash_md5']) && $this->args->getOption('download'),
            ),
            default => $this->io->warning(sprintf('Object "%s" has unknown type "%s"', $uname, $data['type_name'])),
        };

        if (!($entity instanceof ObjectEntity)) {
            return false;
        }

        return $entity;
    }
}
