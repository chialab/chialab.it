<?php
namespace Design\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Chialab\FrontendKit\Model\ObjectsLoader;
use Design\Controller\AppController;
use Chialab\FrontendKit\Traits\GenericActionsTrait;

/**
 * Pages Controller
 */
class PagesController extends AppController
{
    use GenericActionsTrait;

    /**
     * Load home objects.
     *
     * @return void
     */
    public function home(): void
    {
        $root = $this->Publication->getRoot();

        $loader = new ObjectsLoader([
            'objects' => ['include' => 'poster'],
            'documents' => ['include' => 'poster,has_clients'],
            'folders' => ['include' => 'children'],
        ]);
        $folders = $loader->loadRelatedObjects($root->id, 'folders', 'children', null, null, [
            'children' => 4,
        ]);

        $this->set(compact('folders'));
    }

    /**
     * Redirect to external object reference.
     *
     * @param string $uname The uname of the entity.
     * @return \Cake\Http\Response
     */
    public function link(string $uname): Response
    {
        $loader = new ObjectsLoader();
        $object = $loader->loadObject($uname, 'objects');
        $object = $loader->loadObject($object->id, $object->type);

        switch ($object->type) {
            case 'publications':
                return $this->redirect($object->get('public_url'));
        }

        throw new NotFoundException('Missing url');
    }
}
