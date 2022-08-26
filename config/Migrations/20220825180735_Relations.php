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
                'label' => 'Is attach',
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
                'name' => 'has_customers',
                'label' => 'Has customers',
                'inverse_name' => 'customer_of',
                'inverse_label' => 'Customer of',
                'description' => 'Customer of a work',
                'left' => ['documents'],
                'right' => ['profiles'],
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
