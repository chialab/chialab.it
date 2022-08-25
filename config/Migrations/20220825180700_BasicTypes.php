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
