<?php
declare(strict_types=1);

namespace App\Command;

use BEdita\Core\Model\Entity\Folder;
use BEdita\Core\Model\Entity\Link;
use BEdita\Core\Model\Entity\Location;
use BEdita\Core\Model\Entity\Media;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Entity\Profile;
use BEdita\Core\Model\Entity\Stream;
use BEdita\Core\Model\Entity\Tag;
use BEdita\Core\Model\Entity\Translation;
use BEdita\Core\Model\Table\UsersTable;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\Utility\Hash;
use PDO;
use RuntimeException;

/**
 * Helper methods to import contents from a BEdita 3 instance to a BEdita 5 one.
 *
 * @property \BEdita\Core\Model\Table\FoldersTable $Folders
 * @property \BEdita\Core\Model\Table\ObjectsTable $Documents
 * @property \BEdita\Core\Model\Table\ProfilesTable $Profiles
 * @property \BEdita\Core\Model\Table\ObjectsTable $Galleries
 * @property \BEdita\Core\Model\Table\ObjectsTable $Events
 * @property \BEdita\Core\Model\Table\LocationsTable $Locations
 * @property \BEdita\Core\Model\Table\TagsTable $Tags
 * @property \BEdita\Core\Model\Table\CategoriesTable $Categories
 * @property \BEdita\Core\Model\Table\StreamsTable $Streams
 * @property \BEdita\Core\Model\Table\LinksTable $Links
 * @property \BEdita\Core\Model\Table\TranslationsTable $Translations
 * @property \Cake\Database\Connection $sourceConnection
 * @property \Cake\Console\ConsoleIo $io
 * @phpstan-type Be3Folder array{content_order: string, original_id: int, original_uname: string, status: 'on'|'draft'|'off', title: string, description: string, lang: string, extra: string|null, created: string, modified: string}
 * @phpstan-type Be3Content array{type_name: string, original_id: int, original_uname: string, status: 'on'|'draft'|'off', title: string, description: string, body: string, lang: string, relation_params: string|null, extra: string|null, name: string|null, surname: string|null, email: string|null, publish_start: string|null, publish_end: string|null, url: string|null, location_title: string|null, address: string|null, coords: string|null, start_date: string|null, end_date: string|null, date_params: string|null, created: string, modified: string}
 * @phpstan-type Be3Media array{type_name: string, original_id: int, original_uname: string, status: 'on'|'draft'|'off', title: string|null, description: string|null, body: string|null, lang: string, extra: string|null, relation_params: string|null, provider: string|null, provider_uid: string|null, provider_thumbnail: string|null, provider_url: string|null, provider_extra: string|null, name: string, image_width: int|null, image_height: int|null, created: string, modified: string, stream_mime_type: string|null, stream_file_size: int|null, stream_hash_md5: string|null}
 * @phpstan-type Be3Gallery array{status: 'on'|'draft'|'off', original_id: int, original_uname: string, title: string, description: string, lang: string, created: string, modified: string, extra: string}
 * @phpstan-type Be3Category array{label: string, name: string}
 * @phpstan-type Be3Translation array{lang: string, status: 'on'|'draft'|'off', translated_fields: string, created: string, modified: string}
 */
trait ImportBe3Trait
{
    use LocatorAwareTrait;

    /**
     * Parse and URL, encoding all path segments.
     *
     * @param string $url URL to parse
     * @return string The parsed URL
     * @throws \RuntimeException When URL is malformed and/or could not be parsed
     */
    public static function parseURL(string $url): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false) {
            throw new RuntimeException(sprintf('Malformed URL: %s', $url));
        }

        // URL-encode path segments
        if (!empty($parts['path'])) {
            $parts['path'] = implode(
                '/',
                array_map(
                    fn (string $segment): string => rawurlencode(rawurldecode($segment)),
                    explode('/', $parts['path'])
                )
            );
        }

        return sprintf(
            '%s%s%s%s%s%s',
            isset($parts['scheme']) ? sprintf('%s://', $parts['scheme']) : '',
            $parts['host'] ?? '',
            isset($parts['port']) ? sprintf(':%d', $parts['port']) : '',
            $parts['path'] ?? '/',
            isset($parts['query']) ? sprintf('?%s', $parts['query']) : '',
            isset($parts['fragment']) ? sprintf('#%s', $parts['fragment']) : '',
        );
    }

    /**
     * Set base entity data.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity The entity
     * @param Be3Content|Be3Media|Be3Gallery $data The original data
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    protected static function setBaseData(ObjectEntity $entity, array $data): ObjectEntity
    {
        $entity->created_by = UsersTable::ADMIN_USER;
        $entity->modified_by = UsersTable::ADMIN_USER;
        $entity->created = $data['created'] ?? FrozenTime::now(); // @phpstan-ignore-line
        $entity->modified = $data['modified'] ?? FrozenTime::now(); // @phpstan-ignore-line
        if (!empty($data['extra'])) {
            $data['extra'] = json_decode($data['extra'], true);
        }

        /** @var \BEdita\Core\Model\Entity\ObjectEntity $entity */
        $entity = $entity->getTable()->patchEntity($entity, array_merge_recursive($data, [
            'extra' => [
                'imported' => [
                    'id' => $data['original_id'],
                    'uname' => $data['original_uname'],
                ],
            ],
        ]));

        return $entity;
    }

    /**
     * Get a folder, creating it if it does not exist yet.
     *
     * @param string $uname Folder uname
     * @param Be3Folder $data Folder data
     * @return \BEdita\Core\Model\Entity\Folder
     */
    protected function findOrCreateFolder(string $uname, array $data): Folder
    {
        /** @var \BEdita\Core\Model\Entity\Folder|null $folder */
        $folder = $this->Folders->find()
            ->where(compact('uname'))
            ->contain(['Children', 'Translations'])
            ->first();
        if ($folder !== null) {
            return $folder;
        }

        $this->io->verbose(sprintf('Folder "%s" not found, creating it', $uname));
        $folder = static::setBaseData($this->Folders->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\Folder $folder */
        $folder = $this->Folders->saveOrFail($folder);

        return $folder;
    }

    /**
     * Find the imported document, or create a new one.
     *
     * @param string $uname Uname of the document to be imported
     * @param Be3Content $data Data of the document to be imported
     * @param array<string> $contain Relations to load
     * @param string|null $unamePrefix Optional prefix, to be applied if uname already exists
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateDocument(string $uname, array $data, array $contain = [], string|null $unamePrefix = null): ObjectEntity|false
    {
        /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $document */
        $document = $this->Documents->find()
            ->where(compact('uname'))
            ->contain($contain)
            ->first();
        if ($document !== null && $unamePrefix !== null && !Hash::check((array)$document->extra, 'imported')) {
            $uname = sprintf('%s%s', $unamePrefix, $uname);
            $this->io->info(sprintf('Object "%s" already exists, retrying with prefixed uname "%s"', $data['original_uname'], $uname));
            /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $document */
            $document = $this->Documents->find()
                ->where(compact('uname'))
                ->contain($contain)
                ->first();
        }
        if ($document !== null) {
            if (Hash::get((array)$document->extra, 'imported.uname') === $data['original_uname'] && Hash::get((array)$document->extra, 'imported.id') === $data['original_id'] && $document->type === 'documents') {
                $this->io->verbose(sprintf('Reusing already imported document "%s"', $uname));

                return $document;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping document import', $uname));

            return false;
        }

        $document = static::setBaseData($this->Documents->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $document */
        $document = $this->Documents->saveOrFail($document, ['atomic' => false]);

        return $document;
    }

    /**
     * Find the imported document, or create a new one.
     *
     * @param string $uname Uname of the author to be imported
     * @param Be3Content $data Data of the author to be imported
     * @return \BEdita\Core\Model\Entity\Profile|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateAuthor(string $uname, array $data): Profile|false
    {
        /** @var \BEdita\Core\Model\Entity\Profile|null $author */
        $author = $this->Profiles->find()
            ->where(compact('uname'))
            ->contain(['Parents', 'Translations'])
            ->first();
        if ($author !== null) {
            if (Hash::get((array)$author->extra, 'imported.uname') === $data['original_uname'] && $author->type === 'profiles') {
                $this->io->verbose(sprintf('Reusing already imported author "%s"', $uname));

                return $author;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping author import', $uname));

            return false;
        }

        $author = static::setBaseData($this->Profiles->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\Profile $author */
        $author = $this->Profiles->saveOrFail($author, ['atomic' => false]);

        return $author;
    }

    /**
     * Find the imported link, or create a new one.
     *
     * @param string $uname Uname of the link to be imported
     * @param Be3Content $data Data of the link to be imported
     * @return \BEdita\Core\Model\Entity\Link|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateLink(string $uname, array $data): Link|false
    {
        /** @var \BEdita\Core\Model\Entity\Link|null $link */
        $link = $this->Links->find()
            ->where(compact('uname'))
            ->contain(['Parents', 'Translations'])
            ->first();
        if ($link !== null) {
            if (Hash::get((array)$link->extra, 'imported.uname') === $data['original_uname'] && Hash::get((array)$link->extra, 'imported.id') === $data['original_id'] && $link->type === 'links') {
                $this->io->verbose(sprintf('Reusing already imported link "%s"', $uname));

                return $link;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping link import', $uname));

            return false;
        }

        $link = static::setBaseData($this->Links->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\Link $link */
        $link = $this->Links->saveOrFail($link, ['atomic' => false]);

        return $link;
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
            ->contain(['HasMedia', 'Parents', 'Translations'])
            ->first();
        if ($gallery !== null) {
            if (Hash::get((array)$gallery->extra, 'imported.uname') === $data['original_uname'] && Hash::get((array)$gallery->extra, 'imported.id') === $data['original_id'] && $gallery->type === 'galleries') {
                $this->io->verbose(sprintf('Reusing already imported gallery "%s"', $uname));

                return $gallery;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping gallery import', $uname));

            return false;
        }

        $gallery = static::setBaseData($this->Galleries->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $gallery */
        $gallery = $this->Galleries->saveOrFail($gallery, ['atomic' => false]);

        return $gallery;
    }

    /**
     * Find the imported event, or create a new one.
     *
     * @param string $uname Event uname
     * @param Be3Content $data Event data
     * @return \BEdita\Core\Model\Entity\ObjectEntity|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateEvent(string $uname, array $data): ObjectEntity|false
    {
        /** @var \BEdita\Core\Model\Entity\ObjectEntity|null $event */
        $event = $this->Events->find()
            ->where(compact('uname'))
            ->contain(['DateRanges', 'Parents', 'Translations'])
            ->first();
        if ($event !== null) {
            if (Hash::get((array)$event->extra, 'imported.uname') === $data['original_uname'] && $event->type === 'events') {
                $this->io->verbose(sprintf('Reusing already imported event "%s"', $uname));

                return $event;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping event import', $uname));

            return false;
        }

        $event = static::setBaseData($this->Events->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $event */
        $event = $this->Events->saveOrFail($event, ['atomic' => false]);
        if (!empty($data['start_date']) || !empty($data['end_date'])) {
            $dateRange = $this->DateRanges->newEntity([
                'object_id' => $event->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'params' => $data['date_params'],
            ]);
            $dateRange = $this->DateRanges->saveOrFail($dateRange);
            $event->set('date_ranges', [$dateRange]);
            /** @var \BEdita\Core\Model\Entity\ObjectEntity $event */
            $event = $this->Events->saveOrFail($event, ['atomic' => false]);
        }

        return $event;
    }

    /**
     * Find the imported location, or create a new one.
     *
     * @param string $uname Location uname
     * @param Be3Content $data Location data
     * @return \BEdita\Core\Model\Entity\Location|false `false` if an object with the same uname already exists
     */
    protected function findOrCreateLocation(string $uname, array $data): Location|false
    {
        /** @var \BEdita\Core\Model\Entity\Location|null $location */
        $location = $this->Locations->find()
            ->where(compact('uname'))
            ->contain(['Parents', 'Translations'])
            ->first();
        if ($location !== null) {
            if (Hash::get((array)$location->extra, 'imported.uname') === $data['original_uname'] && $location->type === 'locations') {
                $this->io->verbose(sprintf('Reusing already imported location "%s"', $uname));

                return $location;
            }

            $this->io->warning(sprintf('Object "%s" already exists, skipping location import', $uname));

            return false;
        }

        $location = static::setBaseData($this->Locations->newEntity(compact('uname')), $data);
        /** @var \BEdita\Core\Model\Entity\Location $location */
        $location = $this->Locations->saveOrFail($location, ['atomic' => false]);

        return $location;
    }

    /**
     * Find or create a tag.
     *
     * Tags that already exist, either by name or label, are used without extra checks because that's how tags work.
     *
     * @param string $name Name of the tag
     * @param string $label Label of the tag
     * @param string|null $namePrefix Optional name prefix
     * @return \BEdita\Core\Model\Entity\Tag
     */
    protected function findOrCreateTag(string $name, string $label, string|null $namePrefix = null): Tag
    {
        // Tag name can be 50 chars max
        $name = substr(sprintf('%s%s', $namePrefix, $name), 0, 50);
        /** @var \BEdita\Core\Model\Entity\Tag|null $tag */
        $tag = $this->Tags->find()
            ->where(fn (QueryExpression $exp, Query $q) => $exp->or([
                $q->newExpr()->eq('name', $name),
                new FunctionExpression('JSON_CONTAINS', [
                    $this->Tags->aliasField('labels') => 'identifier',
                    sprintf('"%s"', $label), // json-quote the string
                    '$.default',
                ]),
            ]))
            ->first();
        if ($tag === null) {
            $tag = $this->Tags->newEntity(compact('name', 'label'));
            $tag->enabled = true;
            $tag->created = FrozenTime::now(); // @phpstan-ignore-line
            $tag->modified = FrozenTime::now(); // @phpstan-ignore-line
            /** @var \BEdita\Core\Model\Entity\Tag $tag */
            $tag = $this->Tags->saveOrFail($tag, ['atomic' => false]);
        }

        return $tag;
    }

    /**
     * Find or create a translation.
     *
     * @param int $objectId ID of translated object
     * @param Be3Translation $data Translation data
     * @return \BEdita\Core\Model\Entity\Translation
     */
    protected function findOrCreateTranslation(int $objectId, array $data): Translation
    {
        /** @var \BEdita\Core\Model\Entity\Translation|null $translation */
        $translation = $this->Translations->find()
            ->where(fn (QueryExpression $exp): QueryExpression => $exp
                ->eq('object_id', $objectId)
                ->eq('lang', $data['lang']))
            ->first();
        if ($translation !== null) {
            $this->io->verbose(sprintf('Reusing already existing "%s" translation for object %d', $translation->lang, $objectId));

            return $translation;
        }

        if (is_string($data['translated_fields'])) {
            $data['translated_fields'] = json_decode($data['translated_fields'], true);
        }

        $translation = $this->Translations->newEntity(['object_id' => $objectId] + $data);
        $translation->created = FrozenTime::createFromTimestamp((int)$data['created']);
        $translation->modified = FrozenTime::createFromTimestamp((int)$data['modified']);
        $translation->created_by = UsersTable::ADMIN_USER;
        $translation->modified_by = UsersTable::ADMIN_USER;
        /** @var \BEdita\Core\Model\Entity\Translation $translation */
        $translation = $this->Translations->saveOrFail($translation, ['atomic' => false]);

        return $translation;
    }

    /**
     * Find the imported media, reuse an existing one or create a new one.
     *
     * Note: this assumes that the media is imported from a BEdita3 instance and sets the `extra.be3_id` field.
     *
     * @param string $uname Uname of the media to be imported
     * @param Be3Media $data Data of the media to be imported
     * @param string $mediaType Object type of the media to be imported
     * @param bool $download Whether to download the original media, if a URL is provided
     * @return \BEdita\Core\Model\Entity\Media|false `false` on error while creating the stream (see log messages)
     */
    protected function findOrCreateMedia(string $uname, array $data, string $mediaType, bool $download = false): Media|false
    {
        if (!empty($data['provider_url'])) {
            $data['provider_url'] = static::parseUrl($data['provider_url']);
        }

        /** @var \BEdita\Core\Model\Table\MediaTable $Media */
        $Media = match ($mediaType) {
            'audio' => $this->fetchTable('Audio'),
            'b_e_file' => $this->fetchTable('Files'),
            'image' => $this->fetchTable('Images'),
            'video' => $this->fetchTable('Videos'),
            default => throw new RuntimeException(sprintf('Object "%s" has unknown media type "%s"', $uname, $mediaType)),
        };
        /** @var \BEdita\Core\Model\Entity\Media|null $media */
        $media = $Media->find()
            ->where(compact('uname'))
            ->contain(['Streams', 'Parents'])
            ->first();
        if ($media !== null) {
            if (Hash::get((array)$media->extra, 'imported.id') === $data['original_id'] && Hash::get((array)$media->extra, 'imported.uname') === $data['original_uname']) {
                $this->io->verbose(sprintf('Reusing already imported media "%s"', $uname));
                // Handle case where the import command was already run, but without the "download" flag
                if (empty($media->streams) && $download) {
                    if (empty($data['provider_url'])) {
                        $this->io->error(sprintf('Cannot create stream for media "%s" because "provider_url" is empty', $uname));

                        return false;
                    }

                    $stream = $this->createStream($data['provider_url']);
                    if ($stream === false) {
                        $this->io->error(sprintf('Error creating stream for media "%s"', $uname));

                        return false;
                    }

                    $media->streams = [$stream];
                    /** @var \BEdita\Core\Model\Entity\Media $media */
                    $media = $Media->saveOrFail($media, ['atomic' => false]);
                }

                return $media;
            }

            $this->io->verbose(sprintf('Reusing already existing media "%s"', $uname));

            return $media;
        }

        /** @var \BEdita\Core\Model\Entity\Media $media */
        $media = static::setBaseData($Media->newEntity(compact('uname')), $data);
        if ($download) {
            if (empty($data['provider_url'])) {
                $this->io->error(sprintf('Cannot create stream for media "%s" because "provider_url" is empty', $uname));

                return false;
            }

            $stream = $this->createStream($data['provider_url']);
            if ($stream === false) {
                $this->io->error(sprintf('Error creating stream for media "%s"', $data['original_uname']));

                return false;
            }

            $media->streams = [$stream];
        }

        // Audio and video objects are synced from BE3 to Zebra and have this extra property
        if (in_array($mediaType, ['audio', 'video'])) {
            $media->extra['be3_id'] = $data['original_id'];
        }
        /** @var \BEdita\Core\Model\Entity\Media $media */
        $media = $Media->saveOrFail($media, ['atomic' => false]);

        return $media;
    }

    /**
     *  Create a Stream entity from a remote file URL.
     *
     * @param string $url Remote file URL
     * @return \BEdita\Core\Model\Entity\Stream|false `false` on error (see log messages)
     */
    protected function createStream(string $url): Stream|false
    {
        // To assess the mime type, rely on the remote server's content type by doing a HEAD request
        // instead of downloading the whole file
        $ch = curl_init($url);
        if ($ch === false) {
            $this->io->error(sprintf('Error initializing request to remote server for file "%s"', $url));

            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        if (curl_exec($ch) === false) {
            $this->io->error(sprintf('Error sending request to remote server for file "%s": %s', $url, curl_error($ch)));

            return false;
        }

        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (!$contentType) {
            $this->io->error(sprintf('Error obtaining content type from remote server for file "%s"', $url));

            return false;
        }

        $contentType = explode(';', $contentType)[0];
        $fp = fopen($url, 'r');
        if ($fp === false) {
            $this->io->error(sprintf('Error opening stream for image "%s"', $url));

            return false;
        }

        /** @var \BEdita\Core\Model\Entity\Stream $stream */
        $stream = $this->Streams->saveOrFail(
            $this->Streams->newEntity([
                'file_name' => basename($url),
                'mime_type' => $contentType,
                'contents' => $fp,
            ]),
            ['atomic' => false],
        );

        return $stream;
    }

    /**
     * Query to filtered categories.
     *
     * @param array<string> $parents Filter by parent categories
     * @param string|null $objectType Filter by category's object type
     * @return \Cake\ORM\Query
     */
    protected function getCategories(array $parents = [], string|null $objectType = null): Query
    {
        $query = $this->Categories
            ->find();
        if (count($parents) > 0) {
            $query = $query->innerJoin(
                ['pc' => $this->Categories->getTable()],
                [fn (QueryExpression $exp): QueryExpression => $exp
                    ->equalFields('pc.id', $this->Categories->aliasField('parent_id'))
                    ->in('pc.name', $parents)],
            );
        }
        if ($objectType !== null) {
            $query = $query->innerJoin(
                [(string)$this->Categories->ObjectTypes->getAlias() => $this->Categories->ObjectTypes->getTable()],
                [fn (QueryExpression $exp): QueryExpression => $exp
                    ->equalFields($this->Categories->aliasField('object_type_id'), $this->Categories->ObjectTypes->aliasField('id'))
                    ->eq($this->Categories->ObjectTypes->aliasField('name'), $objectType)],
            );
        }

        return $query;
    }

    /**
     * Get multiple categories, by name.
     *
     * @param array<string> $names Names of the categories
     * @param array<string> $parents Parent categories to filter for
     * @param string|null $objectType Category's object type to filter for
     * @return array<\BEdita\Core\Model\Entity\Category>
     */
    protected function getCategoriesByName(array $names, array $parents = [], string|null $objectType = null): array
    {
        return $this->getCategories($parents, $objectType)
            ->where(fn (QueryExpression $exp): QueryExpression => $exp->in($this->Categories->aliasField('name'), $names))
            ->all()
            ->toArray();
    }

    /**
     * Get multiple categories, by label.
     *
     * @param array<string> $labels Labels of the categories
     * @param array<string> $parents Parent categories to filter for
     * @param string|null $objectType Category's object type to filter for
     * @return array<\BEdita\Core\Model\Entity\Category>
     */
    protected function getCategoriesByLabel(array $labels, array $parents = [], string|null $objectType = null): array
    {
        return $this->getCategories($parents, $objectType)
            ->where(fn (QueryExpression $exp): QueryExpression => $exp->in(
                new FunctionExpression('JSON_EXTRACT', [
                    $this->Categories->aliasField('labels') => 'identifier',
                    '$.default',
                ]),
                $labels,
            ))
            ->all()
            ->toArray();
    }

    /**
     * Get a folder from a BEdita 3 database, by nickname.
     *
     * @param string $nickname Folder nickname
     * @return Be3Folder|false `false` if folder not found
     */
    protected function getBe3Folder(string $nickname): array|false
    {
        return $this->sourceConnection->newQuery()
            ->select([
                'content_order' => 's.priority_order',
                'title' => 'o.title',
                'description' => 'o.description',
                'lang' => new FunctionExpression('IF', ['o.lang = "eng"' => 'literal', 'en', 'it']),
                'status' => 'o.status',
                'created' => 'o.created',
                'modified' => 'o.modified',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'extra' => new FunctionExpression('JSON_MERGE_PATCH', [
                    new FunctionExpression('JSON_OBJECT', [
                        'rights', 'o.rights' => 'identifier',
                        'license', 'o.license' => 'identifier',
                        'creator', 'o.creator' => 'identifier',
                        'publisher', 'o.publisher' => 'identifier',
                        'note', 'o.note' => 'identifier',
                    ]),
                    new FunctionExpression('IF', [
                        'a.public_url IS NOT NULL' => 'literal',
                        new FunctionExpression('JSON_OBJECT', [
                            'public_name', 'a.public_name' => 'identifier',
                            'public_url', 'a.public_url' => 'identifier',
                            'staging_url', 'a.staging_url' => 'identifier',
                            'email', 'a.email' => 'identifier',
                        ]),
                        '{}',
                    ]),
                ]),
            ])
            ->from(['o' => 'objects'])
            ->join([
                's' => [
                    'table' => 'sections',
                    'conditions' => 's.id = o.id',
                ],
                'a' => [
                    'type' => 'LEFT',
                    'table' => 'areas',
                    'conditions' => 'a.id = o.id',
                ],
            ])
            ->where(fn (QueryExpression $exp): QueryExpression => $exp->eq('nickname', $nickname))
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the contents of a folder from a BEdita 3 database (no media!).
     *
     * @param int $id Folder ID
     * @param string $order How to order the results
     * @param array<string> $excludedTypes Object types excluded from results
     * @return array<Be3Content>
     */
    protected function getBe3FolderContents(int $id, string $order = 'asc', array $excludedTypes = ['b_e_file', 'image', 'application', 'audio', 'video', 'caption']): array
    {
        $contents = $this->sourceConnection->newQuery()
            ->select([
                'type_name' => 'ot.name',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'title' => 'o.title',
                'description' => 'o.description',
                'body' => new FunctionExpression('COALESCE', [
                    'gp.body' => 'identifier',
                    'pi.body' => 'identifier',
                    'c.body' => 'identifier',
                ]),
                'lang' => new FunctionExpression('IF', ['o.lang = "eng"' => 'literal', 'en', 'it']),
                'status' => 'o.status',
                'publish_start' => 'c.start_date',
                'publish_end' => 'c.end_date',
                'url' => 'li.url',
                'location_title' => 'gt.title',
                'address' => 'gt.address',
                'coords' => new FunctionExpression('CONCAT', [
                    'gt.latitude' => 'identifier',
                    ',',
                    'gt.longitude' => 'identifier',
                ]),
                'start_date' => new FunctionExpression('COALESCE', [
                    'di.start_date' => 'identifier',
                    'c.start_date' => 'identifier',
                ]),
                'end_date' => new FunctionExpression('COALESCE', [
                    'di.end_date' => 'identifier',
                    'c.end_date' => 'identifier',
                ]),
                'date_params' => 'di.params',
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
                    // git_project properties
                    new FunctionExpression('IF', [
                        'gp.name IS NOT NULL' => 'literal',
                        new FunctionExpression('JSON_OBJECT', [
                            'project',
                            new FunctionExpression('JSON_OBJECT', [
                                'name', 'gp.name' => 'identifier',
                                'provider', 'gp.provider' => 'identifier',
                                'url', 'gp.url' => 'identifier',
                                'homepage', 'gp.homepage' => 'identifier',
                                'license', 'gp.license' => 'identifier',
                                'branch', 'gp.branch' => 'identifier',
                                'docs_path', 'gp.docs_path' => 'identifier',
                                'license_path', 'gp.license_path' => 'identifier',
                                'readme_path', 'gp.readme_path' => 'identifier',
                            ]),
                        ]),
                        '{}',
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
                't' => [
                    'table' => 'trees',
                    'conditions' => [
                        't.id = o.id',
                        't.parent_id' => $id,
                    ],
                ],
                'ot' => [
                    'table' => 'object_types',
                    'conditions' => [
                        'ot.id = o.object_type_id',
                        'ot.name NOT IN' => $excludedTypes,
                    ],
                ],
                'pi' => [
                    'table' => 'portfolio_items',
                    'type' => 'LEFT',
                    'conditions' => 'pi.id = o.id',
                ],
                'c' => [
                    'table' => 'contents',
                    'type' => 'LEFT',
                    'conditions' => 'c.id = o.id',
                ],
                'li' => [
                    'table' => 'links',
                    'type' => 'LEFT',
                    'conditions' => 'li.id = o.id',
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
                'gt' => [
                    'type' => 'LEFT',
                    'table' => 'geo_tags',
                    'conditions' => 'gt.object_id = o.id',
                ],
                'di' => [
                    'type' => 'LEFT',
                    'table' => 'date_items',
                    'conditions' => 'di.object_id = o.id',
                ],
                'gp' => [
                    'type' => 'LEFT',
                    'table' => 'git_projects',
                    'conditions' => 'gp.id = o.id',
                ],
            ])
            ->group(['o.id', 't.priority'])
            ->order(['t.priority' => $order])
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Error retrieving contents of folder %d', $id));
        }

        return $contents;
    }

    /**
     * Get the media in a folder from a BEdita 3 database (no contents!).
     *
     * @param int $id Folder ID
     * @param string $order How to order the results
     * @param string|null $providerUrlPrefix Prefix to prepend to the provider URL when it's a relative URL
     * @param array<string> $includedTypes Object types included in results
     * @return array<Be3Media>
     */
    protected function getBe3FolderMedia(int $id, string $order = 'asc', string|null $providerUrlPrefix = null, array $includedTypes = ['b_e_file', 'image', 'application', 'audio', 'video', 'caption']): array
    {
        $media = $this->sourceConnection->newQuery()
            ->select([
                'type_name' => 'ot.name',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'title' => 'o.title',
                'description' => 'o.description',
                'body' => 'c.body',
                'lang' => new FunctionExpression('IF', ['o.lang = "eng"' => 'literal', 'en', 'it']),
                'status' => 'o.status',
                'created' => 'o.created',
                'modified' => 'o.modified',
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
                't' => [
                    'table' => 'trees',
                    'conditions' => [
                        't.id = o.id',
                        't.parent_id' => $id,
                    ],
                ],
                'ot' => [
                    'table' => 'object_types',
                    'conditions' => [
                        'ot.id = o.object_type_id',
                        'ot.name IN' => $includedTypes,
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
            ->group(['o.id', 't.priority'])
            ->order(['t.priority' => $order])
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($media === false) {
            throw new RuntimeException(sprintf('Error retrieving content media of folder %d', $id));
        }

        /** @var array<Be3Media> $media */
        $media = array_map(
            fn (array $object): array => $object + ['relation_params' => null],
            $media,
        );

        return $media;
    }

    /**
     * Get related objects from a BEdita 3 database.
     *
     * @param int $objectId Object ID
     * @param string $relation Relation name
     * @param array<string> $excludedTypes Object types excluded from results
     * @return array<Be3Content>
     */
    protected function getBe3RelatedObjects(int $objectId, string $relation, array $excludedTypes = ['b_e_file', 'image', 'application', 'audio', 'video', 'caption']): array
    {
        $objects = $this->sourceConnection->newQuery()
            ->select([
                'type_name' => 'ot.name',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'body' => new FunctionExpression('COALESCE', ['pi.body' => 'identifier', 'c.body' => 'identifier']),
                'title' => 'o.title',
                'description' => 'o.description',
                'lang' => new FunctionExpression('IF', ['o.lang = "eng"' => 'literal', 'en', 'it']),
                'status' => 'o.status',
                'relation_params' => 'obr.params',
                'publish_start' => 'c.start_date',
                'publish_end' => 'c.end_date',
                'url' => 'li.url',
                'location_title' => 'gt.title',
                'address' => 'gt.address',
                'coords' => new FunctionExpression('CONCAT', [
                    'gt.latitude' => 'identifier',
                    ',',
                    'gt.longitude' => 'identifier',
                ]),
                'start_date' => new FunctionExpression('COALESCE', [
                    'di.start_date' => 'identifier',
                    'c.start_date' => 'identifier',
                ]),
                'end_date' => new FunctionExpression('COALESCE', [
                    'di.end_date' => 'identifier',
                    'c.end_date' => 'identifier',
                ]),
                'date_params' => 'di.params',
                'created' => 'o.created',
                'modified' => 'o.modified',
                'name' => 'ca.name',
                'surname' => 'ca.surname',
                'email' => 'ca.email',
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
                    // git_project properties
                    new FunctionExpression('IF', [
                        'gp.name IS NOT NULL' => 'literal',
                        new FunctionExpression('JSON_OBJECT', [
                            'project',
                            new FunctionExpression('JSON_OBJECT', [
                                'name', 'gp.name' => 'identifier',
                                'provider', 'gp.provider' => 'identifier',
                                'url', 'gp.url' => 'identifier',
                                'homepage', 'gp.homepage' => 'identifier',
                                'license', 'gp.license' => 'identifier',
                                'branch', 'gp.branch' => 'identifier',
                                'docs_path', 'gp.docs_path' => 'identifier',
                                'license_path', 'gp.license_path' => 'identifier',
                                'readme_path', 'gp.readme_path' => 'identifier',
                            ]),
                        ]),
                        '{}',
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
                        'ot.name NOT IN' => $excludedTypes,
                    ],
                ],
                'pi' => [
                    'table' => 'portfolio_items',
                    'type' => 'LEFT',
                    'conditions' => 'pi.id = o.id',
                ],
                'c' => [
                    'table' => 'contents',
                    'type' => 'LEFT',
                    'conditions' => 'c.id = o.id',
                ],
                'ca' => [
                    'table' => 'cards',
                    'type' => 'LEFT',
                    'conditions' => 'ca.id = o.id',
                ],
                'li' => [
                    'table' => 'links',
                    'type' => 'LEFT',
                    'conditions' => 'li.id = o.id',
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
                'gt' => [
                    'type' => 'LEFT',
                    'table' => 'geo_tags',
                    'conditions' => 'gt.object_id = o.id',
                ],
                'di' => [
                    'type' => 'LEFT',
                    'table' => 'date_items',
                    'conditions' => 'di.object_id = o.id',
                ],
                'gp' => [
                    'type' => 'LEFT',
                    'table' => 'git_projects',
                    'conditions' => 'gp.id = o.id',
                ],
            ])
            ->group(['o.id', 'obr.priority'])
            ->orderAsc('obr.priority')
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($objects === false) {
            throw new RuntimeException(sprintf('Error retrieving "%s" objects related to object %d', $relation, $objectId));
        }

        return $objects;
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
     * @param array<string> $includedTypes Object types included in results
     * @return array<Be3Media>
     */
    protected function getBe3RelatedMedia(int $objectId, string $relation, string|null $providerUrlPrefix = null, array $includedTypes = ['b_e_file', 'image', 'application', 'audio', 'video', 'caption']): array
    {
        $media = $this->sourceConnection->newQuery()
            ->select([
                'type_name' => 'ot.name',
                'original_id' => 'o.id',
                'original_uname' => 'o.nickname',
                'title' => 'o.title',
                'description' => 'o.description',
                'body' => 'c.body',
                'lang' => new FunctionExpression('IF', ['o.lang = "eng"' => 'literal', 'en', 'it']),
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
                        'ot.name IN' => $includedTypes,
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
            ->group(['o.id', 'obr.priority'])
            ->orderAsc('obr.priority')
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($media === false) {
            throw new RuntimeException(sprintf('Error retrieving "%s" objects related to object %d', $relation, $objectId));
        }

        return $media;
    }

    /**
     * Get categories of an object from a BEdita 3 database.
     *
     * @param int $objectId Object ID
     * @param array<string> $areas Nicknames of areas to filter for
     * @param string|null $objectType Category's object type to filter for
     * @return array<Be3Category>
     */
    protected function getBe3ObjectCategories(int $objectId, array $areas = [], string|null $objectType = null): array
    {
        $query = $this->sourceConnection->newQuery()
            ->select([
                'label' => 'c.label',
                'name' => 'c.name',
            ])
            ->from(['c' => 'categories'])
            ->innerJoin(['oc' => 'object_categories'], [
                'oc.object_id' => $objectId,
                'oc.category_id = c.id',
            ])
            ->where(['c.object_type_id IS NOT' => null]); // in BEdita 3, this is null if the category is actually a tag
        if (!empty($areas)) {
            $query = $query->innerJoin(['a' => 'objects'], [
                'a.id = c.area_id',
                'a.nickname IN' => $areas,
            ]);
        }
        if ($objectType !== null) {
            $query = $query->innerJoin(['ot' => 'object_types'], [
                'ot.id = c.object_type_id',
                'ot.name' => $objectType,
            ]);
        }

        $categories = $query->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($categories === false) {
            throw new RuntimeException(sprintf('Error retrieving categories for object %d', $objectId));
        }

        return $categories;
    }

    /**
     * Get tags of an object from a BEdita 3 database.
     *
     * @param int $objectId Object ID
     * @return array<Be3Category>
     */
    protected function getBe3ObjectTags(int $objectId): array
    {
        $tags = $this->sourceConnection->newQuery()
            ->select([
                'label' => 'c.label',
                'name' => 'c.name',
            ])
            ->from(['c' => 'categories'])
            ->innerJoin(['oc' => 'object_categories'], [
                'oc.object_id' => $objectId,
                'oc.category_id = c.id',
            ])
            ->where(['c.object_type_id IS' => null]) // in BEdita 3, this is null if the category is actually a tag
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($tags === false) {
            throw new RuntimeException(sprintf('Error retrieving tags for object %d', $objectId));
        }

        return $tags;
    }

    /**
     * Get translations of an object from a BEdita 3 database.
     *
     * @param int $objectId Object ID
     * @return array<Be3Translation>
     */
    protected function getBe3ObjectTranslations(int $objectId): array
    {
        $translations = $this->sourceConnection->newQuery()
            ->select([
                'lang' => new FunctionExpression('IF', ['lt.lang = "eng"' => 'literal', 'en', 'it']), // there are only these 2, so a simple IF is sufficient
                'translated_fields' => new FunctionExpression('JSON_OBJECTAGG', [
                    'lt.name' => 'identifier',
                    'lt.text' => 'identifier',
                ]),
            ])
            ->from(['lt' => 'lang_texts'])
            ->where([
                'lt.object_id' => $objectId,
                'NOT' => [
                    'OR' => [
                        'lt.text' => '',
                        'lt.text IS NULL',
                        'lt.name IN' => ['created_by', 'modified_by'],
                    ],
                ],
            ])
            ->group(['lt.lang'])
            ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);
        if ($translations === false) {
            throw new RuntimeException(sprintf('Error retrieving translations for object %d', $objectId));
        }

        // Extract some object properties from the aggregated fields
        foreach ($translations as &$translation) {
            $translatedFields = json_decode($translation['translated_fields'], true);
            $translation['status'] = Hash::get($translatedFields, 'status', 'draft');
            // "required" was a sort of "TODO" status
            if ($translation['status'] === 'required') {
                $translation['status'] = 'draft';
            }

            $translation['created'] = Hash::get($translatedFields, 'created_on', time());
            $translation['modified'] = Hash::get($translatedFields, 'modified_on', time());
            unset($translatedFields['status'], $translatedFields['created_on'], $translatedFields['modified_on']);
            $translation['translated_fields'] = json_encode($translatedFields);
        }
        unset($translation);

        return $translations;
    }
}
