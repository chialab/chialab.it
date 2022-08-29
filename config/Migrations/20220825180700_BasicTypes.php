<?php
use BEdita\Core\Utility\Resources;
use Migrations\AbstractMigration;

class BasicTypes extends AbstractMigration
{
    protected $create = [
        'object_types' => [
            [
                'name' => 'galleries',
                'singular' => 'gallery',
                'description' => 'Gallery model',
                'associations' => ['Categories', 'Tags'],
            ],
        ],
    ];

    protected $update = [
        'object_types' => [
            [
                'name' => 'documents',
                'associations' => ['Categories', 'Tags'],
            ],
            [
                'name' => 'events',
                'associations' => ['DateRanges', 'Categories', 'Tags'],
            ],
            [
                'name' => 'locations',
                'associations' => ['Categories', 'Tags'],
            ],
            [
                'name' => 'news_item',
                'associations' => ['Categories', 'Tags'],
            ],
            [
                'name' => 'profiles',
                'associations' => ['Categories', 'Tags'],
            ],
        ],
    ];

    protected $revert = [
        'object_types' => [
            [
                'name' => 'documents',
                'associations' => [],
            ],
            [
                'name' => 'events',
                'associations' => [],
            ],
            [
                'name' => 'locations',
                'associations' => [],
            ],
            [
                'name' => 'news',
                'associations' => [],
            ],
            [
                'name' => 'profiles',
                'associations' => [],
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
