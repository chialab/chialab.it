<?php
use BEdita\Core\Utility\Resources;
use Migrations\AbstractMigration;

class Relations extends AbstractMigration
{
    protected $create = [
        'relations' => [
            [
                'name' => 'poster',
                'label' => 'Poster',
                'inverse_name' => 'poster_of',
                'inverse_label' => 'Poster of',
                'description' => 'Image is a poster',
                'left' => [
                    'objects',
                ],
                'right' => [
                    'images',
                    'videos',
                ],
            ],
            [
                'name' => 'attach',
                'label' => 'Has attach',
                'inverse_name' => 'attached_to',
                'inverse_label' => 'Attached to',
                'description' => 'Media is attach',
                'left' => [
                    'events',
                    'folders',
                    'documents',
                    'links',
                    'publications',
                    'galleries',
                    'locations',
                    'news',
                    'profiles',
                ],
                'right' => [
                    'files',
                    'galleries',
                    'images',
                    'videos',
                ],
            ],
            [
                'name' => 'has_location',
                'label' => 'Has location',
                'inverse_name' => 'location_of',
                'inverse_label' => 'Location of',
                'description' => 'Event has location',
                'left' => [
                    'events',
                    'documents',
                ],
                'right' => ['locations'],
            ],
            [
                'name' => 'has_clients',
                'label' => 'Has clients',
                'inverse_name' => 'client_of',
                'inverse_label' => 'Client of',
                'description' => 'Client for a work',
                'left' => ['documents'],
                'right' => ['profiles'],
            ],
            [
                'name' => 'has_contents',
                'label' => 'Has contents',
                'inverse_name' => 'contents_of',
                'inverse_label' => 'Contents of',
                'description' => 'Publication contents',
                'left' => ['publications'],
                'right' => ['folders'],
            ],
            [
                'name' => 'has_contacts',
                'label' => 'Has contacts',
                'inverse_name' => 'contacts_of',
                'inverse_label' => 'Contacts of',
                'description' => 'Publication contacts',
                'left' => ['publications'],
                'right' => ['profiles'],
            ],
            [
                'name' => 'has_privacy_policies',
                'label' => 'Has privacy policies',
                'inverse_name' => 'privacy_police_of',
                'inverse_label' => 'Privacy police of',
                'description' => 'Publication privacy policies',
                'left' => ['publications'],
                'right' => ['documents'],
            ],
            [
                'name' => 'has_newsletters',
                'label' => 'Has newsletters',
                'inverse_name' => 'newsletter_of',
                'inverse_label' => 'Newsletter of',
                'description' => 'Publication newsletters',
                'left' => ['publications'],
                'right' => ['links'],
            ],
            [
                'name' => 'has_featured_clients',
                'label' => 'Has featured clients',
                'inverse_name' => 'featured_client_of',
                'inverse_label' => 'Featured clients of',
                'description' => 'Publication featured clients',
                'left' => ['publications'],
                'right' => ['profiles'],
            ],
            [
                'name' => 'see_also',
                'label' => 'See also',
                'inverse_name' => 'see_also',
                'inverse_label' => 'See also',
                'description' => 'For more details about Item see also other item',
                'left' => ['objects'],
                'right' => ['objects'],
            ],
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function up()
    {
        Resources::save(
            ['create' => $this->create],
            ['connection' => $this->getAdapter()->getCakeConnection()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function down()
    {
        Resources::save(
            ['remove' => $this->create],
            ['connection' => $this->getAdapter()->getCakeConnection()]
        );
    }
}
