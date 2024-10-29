<?php
declare(strict_types=1);

namespace Chialab\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Chialab\FrontendKit\Model\ObjectsLoader;
use Chialab\FrontendKit\Traits\GenericActionsTrait;

/**
 * Pages Controller
 */
class PagesController extends AppController
{
    use GenericActionsTrait {
        fallback as private _fallback;
    }

    /**
     * Load home objects.
     *
     * @return void
     */
    public function home(): void
    {
        $loader = new ObjectsLoader([
            'objects' => ['include' => 'poster'],
            'documents' => ['include' => 'poster,has_media,has_clients,see_also,placeholder'],
            'news' => ['include' => 'poster,has_media,see_also'],
            'folders' => ['include' => 'children|10,poster'],
        ], [
            'children' => 3,
            'has_media' => 3,
            'see_also' => 4,
        ]);

        $root = $this->Publication->getPublication();
        $root = $loader->loadObject($root->uname, 'folders');

        $this->set('folders', $root['children']);
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

    /**
     * Generic object view.
     *
     * @param string $path Object path.
     * @return \Cake\Http\Response
     */
    public function fallback(string $path): Response
    {
        $parts = array_filter(explode('/', $path));
        $object = $this->Objects->loadObject(array_pop($parts), 'objects');

        // include folders from redirect first children
        $include = ['cosacome', 'umani'];

        // redirect to first children of folder, if it's in the include list
        if ($object->type == 'folders' && in_array($object->uname, $include)) {
            $fullObject = $this->Objects->loadObject($object->uname, 'folders', ['include' => 'children|1']);

            if (!empty($fullObject->children)) {
                $first_children = $fullObject->children->toArray()[0];

                return $this->redirect(['_name' => 'pages:objects', 'uname' => $first_children->uname]);
            }
        }

        try {
            return $this->_fallback($path);
        } catch (RecordNotFoundException $e) {
            // If path is wrong, but the requested object exists, redirect to `/objects/{uname}`.
            // First, read last path element.
            $parts = array_filter(explode('/', $path));
            $object = array_pop($parts);
            try {
                // Now, try to load the object.
                $object = $this->Objects->loadObject($object);

                // If we reach this point, the object does exist, but the path at which it was being accessed was wrong.
                // Try to redirect to `/objects/{object}` to see if we can display it somehow.
                return $this->redirect(['_name' => 'pages:objects', 'uname' => $object->uname]);
            } catch (RecordNotFoundException $err) {
                // No object exists under this name. Re-throw original exception.
                throw $e;
            }
        }
    }
}
