<?php
declare(strict_types=1);

namespace Chialab\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Chialab\FrontendKit\Model\ObjectsLoader;
use Chialab\FrontendKit\Model\TreeLoader;
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
            'documents' => ['include' => 'poster,has_media,has_clients,see_also'],
            'news' => ['include' => 'poster,has_media,see_also'],
            'folders' => ['include' => 'children'],
        ], [
            'children' => 3,
            'has_media' => 3,
            'see_also' => 3,
        ]);
        $treeLoader = new TreeLoader($loader);
        $folders = $treeLoader->loadMenu((string)$root->id)->children;

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
        $object = $loader->loadObject((string)$object->id, $object->type);

        switch ($object->type) {
            case 'links':
                return $this->redirect($object->get('url'));
        }

        throw new NotFoundException('Missing url');
    }
}
