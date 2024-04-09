<?php
namespace Design\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Chialab\FrontendKit\Model\ObjectsLoader;
use Chialab\FrontendKit\Model\TreeLoader;
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
        $root = $this->Publication->getPublication();

        $loader = new ObjectsLoader([
            'objects' => ['include' => 'poster'],
            'documents' => ['include' => 'poster,has_clients,see_also,attach'],
            'news' => ['include' => 'poster,see_also,attach'],
            'folders' => ['include' => 'children'],
        ], [
            'children' => 3,
            'see_also' => 3,
            'attach' => 3,
        ]);
        $treeLoader = new TreeLoader($loader);
        $folders = $treeLoader->loadMenu($root->id)->children;

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
