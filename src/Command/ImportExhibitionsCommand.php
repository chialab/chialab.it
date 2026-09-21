<?php
declare(strict_types=1);

namespace App\Command;

use BEdita\Core\Model\Action\AddRelatedObjectsAction;
use BEdita\Core\Model\Entity\Category;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Entity\Stream;
use BEdita\Core\Model\Entity\Tag;
use BEdita\Core\Model\Table\CategoriesTable;
use BEdita\Core\Model\Table\LinksTable;
use BEdita\Core\Model\Table\MediaTable;
use BEdita\Core\Model\Table\ObjectsTable;
use BEdita\Core\Model\Table\ObjectTypesTable;
use BEdita\Core\Model\Table\ProfilesTable;
use BEdita\Core\Model\Table\StreamsTable;
use BEdita\Core\Model\Table\TagsTable;
use BEdita\Core\Model\Table\UsersTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Exception;
use UnexpectedValueException;

/**
 * Import exhibitions (and their related media/documents/profiles/links/galleries) from another
 * BEdita 5 installation sharing the same object model (e.g. the BCBF galleries website).
 *
 * Imported objects are left without a parent folder in the content tree: attach them where needed
 * afterwards. Running the command again for an already-imported exhibition uname is a no-op.
 */
class ImportExhibitionsCommand extends Command
{
    protected const IMPORT_SOURCE = 'bcbf2';

    protected Connection $sourceConnection;
    protected ConsoleIo $io;

    protected ObjectsTable $Objects;
    protected ObjectsTable $Exhibitions;
    protected ObjectsTable $Documents;
    protected ObjectsTable $Galleries;
    protected ProfilesTable $Profiles;
    protected LinksTable $Links;
    protected MediaTable $Images;
    protected MediaTable $Videos;
    protected MediaTable $Files;
    protected StreamsTable $Streams;
    protected CategoriesTable $Categories;
    protected TagsTable $Tags;
    protected ObjectTypesTable $ObjectTypes;

    /**
     * @var array<string, int>
     */
    protected array $objectTypeIdCache = [];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription([
                'Import one or more exhibitions from another BEdita 5 installation, along with the',
                'media, documents, profiles, links and galleries they reference.',
                'Usage: bin/cake import_exhibitions <uname> [<uname> ...] [-C <connection>] [--source-root <path>]',
            ])
            ->addOption('source-connection', [
                'short' => 'C',
                'help' => 'Name of the connection to use for the source database.',
                'choices' => ConnectionManager::configured(),
                'default' => static::IMPORT_SOURCE,
            ])
            ->addOption('source-root', [
                'help' => 'Absolute path to the source application root, used to locate local media files on disk.',
                'default' => '/Users/andrea/WebProjects/BCBFgalleries',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        foreach (
            [
            'Objects', 'Exhibitions', 'Documents', 'Galleries', 'Profiles', 'Links',
            'Images', 'Videos', 'Files', 'Streams', 'Categories', 'Tags', 'ObjectTypes',
            ] as $tableName
        ) {
            $this->{$tableName} = $this->fetchTable($tableName); // @phpstan-ignore-line
            if ($this->{$tableName}->hasBehavior('Timestamp')) {
                $this->{$tableName}->removeBehavior('Timestamp');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int|null
    {
        $this->io = $io;
        $unames = $args->getArguments();
        if (empty($unames)) {
            $io->error('Provide at least one exhibition uname to import.');

            return static::CODE_ERROR;
        }

        $sourceConnection = ConnectionManager::get((string)$args->getOption('source-connection'));
        if (!$sourceConnection instanceof Connection) {
            throw new UnexpectedValueException(
                sprintf(
                    'Invalid connection type: expected "%s", got "%s"',
                    Connection::class,
                    get_debug_type($sourceConnection),
                ),
            );
        }
        $this->sourceConnection = $sourceConnection;

        $sourceRoot = rtrim((string)$args->getOption('source-root'), '/');
        $connection = $this->Exhibitions->getConnection();
        $failures = 0;
        foreach ($unames as $uname) {
            try {
                $result = $connection->transactional(
                    fn (): ObjectEntity|false => $this->importExhibition((string)$uname, $sourceRoot),
                );
                if ($result === false) {
                    $failures++;
                }
            } catch (Exception $e) {
                $failures++;
                $io->error(sprintf('Error importing exhibition "%s": %s', $uname, $e->getMessage()));
            }
        }

        return $failures === 0 ? static::CODE_SUCCESS : static::CODE_ERROR;
    }

    /**
     * Import a single exhibition, along with its poster, categories, tags and items.
     *
     * @param string $uname Uname of the exhibition in the source database.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importExhibition(string $uname, string $sourceRoot): ObjectEntity|false
    {
        $row = $this->fetchSourceObject($uname, true);
        if ($row === false || $row['type_name'] !== 'exhibitions') {
            $this->io->error(sprintf('Exhibition "%s" not found in source database', $uname));

            return false;
        }

        $existing = $this->findImported($this->Exhibitions, $uname);
        if ($existing !== null) {
            $this->io->info(sprintf('Exhibition "%s" already imported as #%d, skipping', $uname, $existing->id));

            return $existing;
        }

        $this->io->info(sprintf('Importing exhibition "%s"', $uname));
        $entity = $this->setBaseFields($this->Exhibitions->newEntity(['uname' => $this->uniqueUname($uname)]), $row);
        $entity->body = $row['body'];
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $entity */
        $entity = $this->Exhibitions->saveOrFail($entity, ['atomic' => false]);

        $entity = $this->attachPoster($entity, (int)$row['id'], $sourceRoot);
        $entity = $this->attachCategories($entity, (int)$row['id']);
        $entity = $this->attachTags($entity, (int)$row['id']);

        $items = [];
        foreach ($this->fetchSourceRelated((int)$row['id'], 'exhibition_items') as $related) {
            $itemRow = $this->fetchSourceObject((int)$related['id']);
            if ($itemRow === false) {
                continue;
            }

            $item = $this->importItem($itemRow, $sourceRoot);
            if ($item !== false) {
                $items[] = $item;
            }
        }
        if (!empty($items)) {
            $action = new AddRelatedObjectsAction(['association' => $this->Exhibitions->getAssociation('ExhibitionItems')]);
            $action(['entity' => $entity, 'relatedEntities' => $items]);
        }

        $this->io->success(sprintf('Imported exhibition "%s" (#%d) with %d item(s)', $uname, $entity->id, count($items)));

        return $entity;
    }

    /**
     * Import a single exhibition item, dispatching by object type.
     *
     * @param array<string, mixed> $row Source object row (with `type_name`).
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importItem(array $row, string $sourceRoot): ObjectEntity|false
    {
        return match ($row['type_name']) {
            'images', 'videos', 'files' => $this->importMedia($row, $sourceRoot),
            'documents' => $this->importDocument($row, $sourceRoot),
            'profiles' => $this->importProfile($row, $sourceRoot),
            'links' => $this->importLink($row),
            'galleries' => $this->importGallery($row, $sourceRoot),
            default => $this->warnUnsupported($row),
        };
    }

    /**
     * Import a document and its poster/media.
     *
     * @param array<string, mixed> $row Source object row.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importDocument(array $row, string $sourceRoot): ObjectEntity|false
    {
        $existing = $this->findImported($this->Documents, $row['uname']);
        if ($existing !== null) {
            return $existing;
        }

        $entity = $this->setBaseFields($this->Documents->newEntity(['uname' => $this->uniqueUname($row['uname'])]), $row);
        $entity->body = $row['body'];
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $entity */
        $entity = $this->Documents->saveOrFail($entity, ['atomic' => false]);

        $entity = $this->attachPoster($entity, (int)$row['id'], $sourceRoot);

        return $this->attachHasMedia($entity, (int)$row['id'], $sourceRoot);
    }

    /**
     * Import a profile (with its extra profile fields) and its poster/media.
     *
     * @param array<string, mixed> $row Source object row.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importProfile(array $row, string $sourceRoot): ObjectEntity|false
    {
        $existing = $this->findImported($this->Profiles, $row['uname']);
        if ($existing !== null) {
            return $existing;
        }

        $extra = (array)$this->sourceConnection->selectQuery()
            ->select([
                'name', 'surname', 'email', 'person_title', 'gender', 'birthdate', 'deathdate',
                'company', 'company_name', 'company_kind', 'street_address', 'city', 'zipcode',
                'country', 'state_name', 'phone', 'website', 'national_id_number', 'vat_number', 'pseudonym',
            ])
            ->from('profiles')
            ->where(['id' => $row['id']])
            ->execute()
            ->fetch('assoc');

        /** @var \BEdita\Core\Model\Entity\Profile $entity */
        $entity = $this->setBaseFields($this->Profiles->newEntity(['uname' => $this->uniqueUname($row['uname'])]), $row);
        foreach ($extra as $field => $value) {
            $entity->{$field} = $value;
        }
        /** @var \BEdita\Core\Model\Entity\Profile $entity */
        $entity = $this->Profiles->saveOrFail($entity, ['atomic' => false]);

        $entity = $this->attachPoster($entity, (int)$row['id'], $sourceRoot);

        return $this->attachHasMedia($entity, (int)$row['id'], $sourceRoot);
    }

    /**
     * Import a link.
     *
     * @param array<string, mixed> $row Source object row.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importLink(array $row): ObjectEntity|false
    {
        $existing = $this->findImported($this->Links, $row['uname']);
        if ($existing !== null) {
            return $existing;
        }

        $url = $this->sourceConnection->selectQuery()
            ->select(['url'])
            ->from('links')
            ->where(['id' => $row['id']])
            ->execute()
            ->fetchColumn(0);

        /** @var \BEdita\Core\Model\Entity\Link $entity */
        $entity = $this->setBaseFields($this->Links->newEntity(['uname' => $this->uniqueUname($row['uname'])]), $row);
        $entity->url = $url !== false ? (string)$url : null;
        /** @var \BEdita\Core\Model\Entity\Link $entity */
        $entity = $this->Links->saveOrFail($entity, ['atomic' => false]);

        return $entity;
    }

    /**
     * Import a gallery (with its layout/columns/crop custom properties) and its media.
     *
     * @param array<string, mixed> $row Source object row.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importGallery(array $row, string $sourceRoot): ObjectEntity|false
    {
        $existing = $this->findImported($this->Galleries, $row['uname']);
        if ($existing !== null) {
            return $existing;
        }

        $entity = $this->setBaseFields($this->Galleries->newEntity(['uname' => $this->uniqueUname($row['uname'])]), $row);
        if (!empty($row['custom_props'])) {
            $entity->custom_props = json_decode((string)$row['custom_props'], true) ?: [];
        }
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $entity */
        $entity = $this->Galleries->saveOrFail($entity, ['atomic' => false]);

        return $this->attachHasMedia($entity, (int)$row['id'], $sourceRoot);
    }

    /**
     * Import a media object (image/video/file), copying its physical file(s) from the source app.
     *
     * @param array<string, mixed> $row Source object row.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false
     */
    protected function importMedia(array $row, string $sourceRoot): ObjectEntity|false
    {
        $table = match ($row['type_name']) {
            'images' => $this->Images,
            'videos' => $this->Videos,
            'files' => $this->Files,
            default => null,
        };
        if ($table === null) {
            return $this->warnUnsupported($row);
        }

        $existing = $this->findImported($table, $row['uname']);
        if ($existing !== null) {
            return $existing;
        }

        $streams = [];
        foreach ($this->fetchSourceStreams((int)$row['id']) as $streamRow) {
            $stream = $this->copyStream($streamRow, $sourceRoot);
            if ($stream !== false) {
                $streams[] = $stream;
            }
        }
        if (empty($streams)) {
            // Keep the media object even without its physical file: the file can be backfilled later,
            // but the object (and its relations, categories, tags...) would otherwise be lost entirely.
            $this->io->warning(sprintf('No usable stream found for media "%s" (#%d), importing without a file', $row['uname'], $row['id']));
        }

        /** @var \BEdita\Core\Model\Entity\Media $entity */
        $entity = $this->setBaseFields($table->newEntity(['uname' => $this->uniqueUname($row['uname'])]), $row);
        if (!empty($streams)) {
            $entity->streams = $streams;
        }
        /** @var \BEdita\Core\Model\Entity\Media $entity */
        $entity = $table->saveOrFail($entity, ['atomic' => false]);

        return $entity;
    }

    /**
     * Copy a stream's physical file from the source app's local filesystem and create a new Stream entity for it.
     *
     * @param array<string, mixed> $streamRow Source stream row.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\Stream|false
     */
    protected function copyStream(array $streamRow, string $sourceRoot): Stream|false
    {
        $relativePath = (string)preg_replace('#^[a-zA-Z0-9_-]+://#', '', (string)$streamRow['uri']);
        $path = sprintf('%s/webroot/_files/%s', $sourceRoot, $relativePath);
        if (!is_file($path)) {
            $this->io->warning(sprintf('Source file not found, skipping: %s', $path));

            return false;
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $this->io->warning(sprintf('Cannot open source file, skipping: %s', $path));

            return false;
        }

        /** @var \BEdita\Core\Model\Entity\Stream $stream */
        $stream = $this->Streams->saveOrFail(
            $this->Streams->newEntity([
                'file_name' => $streamRow['file_name'],
                'mime_type' => $streamRow['mime_type'],
                'contents' => $fh,
            ]),
            ['atomic' => false],
        );

        return $stream;
    }

    /**
     * Attach the poster (if any) of a source object to the imported entity.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Imported entity.
     * @param int $sourceId Source object id.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected function attachPoster(ObjectEntity $entity, int $sourceId, string $sourceRoot): ObjectEntity
    {
        $related = $this->fetchSourceRelated($sourceId, 'poster');
        if (empty($related)) {
            return $entity;
        }

        $posterRow = $this->fetchSourceObject((int)$related[0]['id']);
        if ($posterRow === false) {
            return $entity;
        }

        $poster = $this->importMedia($posterRow, $sourceRoot);
        if ($poster === false) {
            return $entity;
        }

        $action = new AddRelatedObjectsAction(['association' => $entity->getTable()->getAssociation('Poster')]);
        $action(['entity' => $entity, 'relatedEntities' => [$poster]]);

        return $entity;
    }

    /**
     * Attach the "has_media" related media (if any) of a source object to the imported entity.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Imported entity.
     * @param int $sourceId Source object id.
     * @param string $sourceRoot Absolute path to the source application root.
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected function attachHasMedia(ObjectEntity $entity, int $sourceId, string $sourceRoot): ObjectEntity
    {
        $mediaEntities = [];
        foreach ($this->fetchSourceRelated($sourceId, 'has_media') as $related) {
            $mediaRow = $this->fetchSourceObject((int)$related['id']);
            if ($mediaRow === false) {
                continue;
            }

            $media = $this->importMedia($mediaRow, $sourceRoot);
            if ($media !== false) {
                $mediaEntities[] = $media;
            }
        }
        if (!empty($mediaEntities)) {
            $action = new AddRelatedObjectsAction(['association' => $entity->getTable()->getAssociation('HasMedia')]);
            $action(['entity' => $entity, 'relatedEntities' => $mediaEntities]);
        }

        return $entity;
    }

    /**
     * Attach the categories (if any) of a source object to the imported entity, creating matching
     * categories in the target database if they don't already exist for that object type.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Imported entity.
     * @param int $sourceId Source object id.
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected function attachCategories(ObjectEntity $entity, int $sourceId): ObjectEntity
    {
        $rows = $this->fetchSourceCategories($sourceId);
        if (empty($rows)) {
            return $entity;
        }

        $categories = array_map(
            fn (array $row): Category => $this->findOrCreateCategory($entity->type, $row),
            $rows,
        );
        $action = new AddRelatedObjectsAction(['association' => $entity->getTable()->getAssociation('Categories')]);
        $action(['entity' => $entity, 'relatedEntities' => $categories]);

        return $entity;
    }

    /**
     * Attach the tags (if any) of a source object to the imported entity, creating matching tags
     * in the target database if they don't already exist.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Imported entity.
     * @param int $sourceId Source object id.
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected function attachTags(ObjectEntity $entity, int $sourceId): ObjectEntity
    {
        $rows = $this->fetchSourceTags($sourceId);
        if (empty($rows)) {
            return $entity;
        }

        $tags = array_map(fn (array $row): Tag => $this->findOrCreateTag($row), $rows);
        $action = new AddRelatedObjectsAction(['association' => $entity->getTable()->getAssociation('Tags')]);
        $action(['entity' => $entity, 'relatedEntities' => $tags]);

        return $entity;
    }

    /**
     * Find or create a category with the given name, scoped to the given object type.
     *
     * @param string $typeName Object type name (e.g. "exhibitions").
     * @param array<string, mixed> $row Source category row (`name`, `labels`).
     * @return \BEdita\Core\Model\Entity\Category
     */
    protected function findOrCreateCategory(string $typeName, array $row): Category
    {
        $objectTypeId = $this->objectTypeId($typeName);
        /** @var \BEdita\Core\Model\Entity\Category|null $category */
        $category = $this->Categories->find()
            ->where(['object_type_id' => $objectTypeId, 'name' => $row['name']])
            ->first();
        if ($category !== null) {
            return $category;
        }

        $labels = is_string($row['labels']) ? json_decode($row['labels'], true) : $row['labels'];
        /** @var \BEdita\Core\Model\Entity\Category $category */
        $category = $this->Categories->saveOrFail(
            $this->Categories->newEntity(['object_type_id' => $objectTypeId, 'name' => $row['name'], 'labels' => $labels]),
            ['atomic' => false],
        );

        return $category;
    }

    /**
     * Find or create a tag with the given name.
     *
     * @param array<string, mixed> $row Source tag row (`name`, `labels`).
     * @return \BEdita\Core\Model\Entity\Tag
     */
    protected function findOrCreateTag(array $row): Tag
    {
        /** @var \BEdita\Core\Model\Entity\Tag|null $tag */
        $tag = $this->Tags->find()->where(['name' => $row['name']])->first();
        if ($tag !== null) {
            return $tag;
        }

        $labels = is_string($row['labels']) ? json_decode($row['labels'], true) : $row['labels'];
        /** @var \BEdita\Core\Model\Entity\Tag $tag */
        $tag = $this->Tags->saveOrFail(
            $this->Tags->newEntity(['name' => $row['name'], 'labels' => $labels]),
            ['atomic' => false],
        );

        return $tag;
    }

    /**
     * Set the fields common to every object type (title, description, status, lang, timestamps,
     * and the "imported" marker used to make this command idempotent).
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Entity to set base data on.
     * @param array<string, mixed> $row Source object row.
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected function setBaseFields(ObjectEntity $entity, array $row): ObjectEntity
    {
        $entity->title = $row['title'];
        $entity->description = $row['description'];
        $entity->status = $row['status'];
        $entity->lang = $row['lang'];
        $entity->created = $row['created'];
        $entity->modified = $row['modified'];
        $entity->created_by = UsersTable::ADMIN_USER;
        $entity->modified_by = UsersTable::ADMIN_USER;
        $entity->extra = ['imported' => [
            'source' => static::IMPORT_SOURCE,
            'id' => (int)$row['id'],
            'uname' => $row['uname'],
        ]];

        return $entity;
    }

    /**
     * Find an entity previously imported from the given source uname, if any.
     *
     * @param \BEdita\Core\Model\Table\ObjectsTable|\BEdita\Core\Model\Table\ProfilesTable|\BEdita\Core\Model\Table\LinksTable|\BEdita\Core\Model\Table\MediaTable $table Target table.
     * @param string $sourceUname Uname of the object in the source database.
     * @return \BEdita\Core\Model\Entity\ObjectEntity|null
     */
    protected function findImported(ObjectsTable|ProfilesTable|LinksTable|MediaTable $table, string $sourceUname): ObjectEntity|null
    {
        /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $found */
        $found = $table->find()
            ->where([
                "extra->>'$.imported.source'" => static::IMPORT_SOURCE,
                "extra->>'$.imported.uname'" => $sourceUname,
            ])
            ->first();

        return $found;
    }

    /**
     * Build a uname that doesn't already exist in the target database, appending a suffix if needed.
     *
     * @param string $uname Preferred uname.
     * @return string
     */
    protected function uniqueUname(string $uname): string
    {
        $candidate = $uname;
        $i = 1;
        while ($this->Objects->exists(['uname' => $candidate])) {
            $candidate = sprintf('%s-%s-%d', $uname, static::IMPORT_SOURCE, $i++);
        }

        return $candidate;
    }

    /**
     * Get (and cache) the target database's object_type_id for a given type name.
     *
     * @param string $typeName Object type name.
     * @return int
     */
    protected function objectTypeId(string $typeName): int
    {
        if (!isset($this->objectTypeIdCache[$typeName])) {
            /** @var \BEdita\Core\Model\Entity\ObjectType $objectType */
            $objectType = $this->ObjectTypes->find()
                ->where(['name' => $typeName])
                ->firstOrFail();
            $this->objectTypeIdCache[$typeName] = (int)$objectType->id;
        }

        return $this->objectTypeIdCache[$typeName];
    }

    /**
     * Log a warning for an object of an unsupported type and return `false`.
     *
     * @param array<string, mixed> $row Source object row.
     * @return false
     */
    protected function warnUnsupported(array $row): bool
    {
        $this->io->warning(sprintf('Unsupported object type "%s" for #%d ("%s"), skipping', $row['type_name'], $row['id'], $row['uname']));

        return false;
    }

    /**
     * Fetch a single object row (plus its `type_name`) from the source database, by id or uname.
     *
     * @param string|int $idOrUname Object id or uname.
     * @param bool $byUname Whether `$idOrUname` is a uname (true) or an id (false).
     * @return array<string, mixed>|false
     */
    protected function fetchSourceObject(int|string $idOrUname, bool $byUname = false): array|false
    {
        $query = $this->sourceConnection->selectQuery()
            ->select(['o.*', 'ot.name AS type_name'])
            ->from(['o' => 'objects'])
            ->innerJoin(['ot' => 'object_types'], 'ot.id = o.object_type_id')
            ->where(['o.deleted' => 0])
            ->where($byUname ? ['o.uname' => $idOrUname] : ['o.id' => $idOrUname]);

        $row = $query->execute()->fetch('assoc');

        return $row ?: false;
    }

    /**
     * Fetch the streams of an object from the source database.
     *
     * @param int $objectId Source object id.
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSourceStreams(int $objectId): array
    {
        return $this->sourceConnection->selectQuery()
            ->select('*')
            ->from('streams')
            ->where(['object_id' => $objectId])
            ->execute()
            ->fetchAll('assoc') ?: [];
    }

    /**
     * Fetch the objects related to a given left object via a named relation, ordered by priority.
     *
     * @param int $leftId Left object id.
     * @param string $relation Relation name.
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSourceRelated(int $leftId, string $relation): array
    {
        return $this->sourceConnection->selectQuery()
            ->select(['o.id', 'ot.name AS type_name'])
            ->from(['orl' => 'object_relations'])
            ->innerJoin(['r' => 'relations'], 'r.id = orl.relation_id')
            ->innerJoin(['o' => 'objects'], 'o.id = orl.right_id')
            ->innerJoin(['ot' => 'object_types'], 'ot.id = o.object_type_id')
            ->where(['r.name' => $relation, 'orl.left_id' => $leftId, 'o.deleted' => 0])
            ->order(['orl.priority' => 'ASC'])
            ->execute()
            ->fetchAll('assoc') ?: [];
    }

    /**
     * Fetch the categories of an object from the source database.
     *
     * @param int $objectId Source object id.
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSourceCategories(int $objectId): array
    {
        return $this->sourceConnection->selectQuery()
            ->select(['c.name', 'c.labels'])
            ->from(['oc' => 'object_categories'])
            ->innerJoin(['c' => 'categories'], 'c.id = oc.category_id')
            ->where(['oc.object_id' => $objectId])
            ->execute()
            ->fetchAll('assoc') ?: [];
    }

    /**
     * Fetch the tags of an object from the source database.
     *
     * @param int $objectId Source object id.
     * @return array<int, array<string, mixed>>
     */
    protected function fetchSourceTags(int $objectId): array
    {
        return $this->sourceConnection->selectQuery()
            ->select(['t.name', 't.labels'])
            ->from(['ot' => 'object_tags'])
            ->innerJoin(['t' => 'tags'], 't.id = ot.tag_id')
            ->where(['ot.object_id' => $objectId])
            ->execute()
            ->fetchAll('assoc') ?: [];
    }
}
