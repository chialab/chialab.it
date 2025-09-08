<?php
declare(strict_types=1);

namespace Chialab\View\Cell;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\View\Cell;
use Chialab\FrontendKit\Model\ObjectsLoader;

class GalleryCell extends Cell
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
        ]);
    }

    /**
     * Load and render the default card.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to render.
     * @param array|null $props The properties to pass to the view.
     * @return void
     */
    public function display(ObjectEntity $object, array|null $props = []): void
    {
        $data = $this->loader->loadRelatedObjects((string)$object->id, $object->type, 'has_media');
        $object->set('has_media', $data);

        $this->set(compact('object') + $props);
    }
}
