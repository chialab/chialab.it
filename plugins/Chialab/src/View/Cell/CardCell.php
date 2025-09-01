<?php
declare(strict_types=1);

namespace Chialab\View\Cell;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\View\Cell;
use Chialab\FrontendKit\Model\ObjectsLoader;

class CardCell extends Cell
{
    /**
     * BEdita objects loader.
     *
     * @var \Chialab\FrontendKit\Model\ObjectsLoader
     */
    protected ObjectsLoader $loader;

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loader = new ObjectsLoader([
            'objects' => ['include' => 'poster|1'],
            'galleries' => ['include' => 'poster|1,has_media'],
        ], [
            'has_media' => 2,
        ]);
    }

    /**
     * Load relations into the object.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to load relations into.
     * @param array $relations The relations to load.
     * @return void
     */
    protected function loadRelated(ObjectEntity $object, array $relations): void
    {
        foreach ($relations as $relationName) {
            $data = $this->loader->loadRelatedObjects((string)$object->id, $object->type, $relationName);
            $object->set($relationName, $data);
        }
    }

    /**
     * Load and render the default card.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to render.
     * @param array|null $props The properties to pass to the view.
     * @return void
     */
    public function display(ObjectEntity $object, ?array $props = []): void
    {
        switch ($object->get('type')) {
            case 'documents':
                $this->loadRelated($object, ['has_clients']);
                break;
        }

        $this->set(compact('object') + $props);
    }

    /**
     * Load and render a wide card.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to render.
     * @param array|null $props The properties to pass to the view.
     * @return void
     */
    public function wide(ObjectEntity $object, ?array $props = []): void
    {
        $this->display($object, $props);
    }

    /**
     * Load and render a collapsable card.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to render.
     * @param array|null $props The properties to pass to the view.
     * @return void
     */
    public function collapsable(ObjectEntity $object, ?array $props = []): void
    {
        switch ($object->get('type')) {
            case 'documents':
            case 'news':
                $this->loadRelated($object, ['has_media', 'see_also']);
                break;
            case 'events':
                $this->loadRelated($object, ['has_links']);
                break;
        }

        $this->display($object, $props);
    }
}
