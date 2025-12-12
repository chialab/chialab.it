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
            'objects' => ['include' => 'poster|1'],
            'folders' => ['include' => 'poster|1,featured'],
        ], [
            'featured' => 3,
            'has_media' => 4,
            'see_also' => 4,
        ]);

        $root = $this->Publication->getPublication();
        $root = $loader->loadObject($root->uname, 'folders', [
            'include' => 'children,poster',
        ], [
            'children' => 2,
        ]);

        $this->set('folders', $root['children']);
    }

    /**
     * Load "works" section.
     *
     * @param string|null $path Object path.
     * @return \Cake\Http\Response
     */
    public function works(string|null $path = null): Response
    {
        if ($path !== null) {
            return $this->fallback(sprintf('works/%s', $path));
        }

        $this->components()->unload('Objects');
        $this->loadComponent('Chialab/FrontendKit.Objects', [
            'objectTypesConfig' => [
                'objects' => ['include' => 'poster|1'],
                'documents' => ['include' => 'poster|1,has_clients'],
            ],
        ]);

        return $this->fallback('works');
    }

    /**
     * Load "tatzebao" section.
     *
     * @param string|null $path Object path.
     * @return \Cake\Http\Response
     */
    public function tatzebao(string|null $path = null): Response
    {
        if ($path !== null) {
            $tatzebao = $this->Objects->loadRelatedObjects('tatzebao', 'folders', 'featured')->toArray();
            $this->set(compact('tatzebao'));

            return $this->fallback(sprintf('tatzebao/%s', $path));
        }

        return $this->fallback('tatzebao');
    }

    /**
     * Load special section object "cosacome".
     *
     * @param string|null $path Object path.
     * @return \Cake\Http\Response
     */
    public function cosacome(string|null $path = null): Response
    {
        if ($path === null) {
            $firstChild = $this->Objects->loadRelatedObjects('cosacome', 'folders', 'children')->first();
            if (!empty($firstChild)) {
                return $this->redirect(['_name' => 'pages:objects', 'uname' => $firstChild->uname]);
            }
        }

        $toc = $this->Objects->loadRelatedObjects('cosacome', 'folders', 'children', null, [], [])->toArray();
        $this->set(compact('toc'));

        return $this->fallback(sprintf('cosacome/%s', $path));
    }

    /**
     * Load special section object "umani".
     *
     * @param string|null $path Object path.
     * @return \Cake\Http\Response
     */
    public function umani(string|null $path = null): Response
    {
        if ($path === null) {
            $firstChild = $this->Objects->loadRelatedObjects('umani', 'folders', 'children')->first();
            if (!empty($firstChild)) {
                return $this->redirect(['_name' => 'pages:objects', 'uname' => $firstChild->uname]);
            }
        }

        $toc = $this->Objects->loadRelatedObjects('umani', 'folders', 'children', null, [], [])->toArray();
        $this->set(compact('toc'));

        return $this->fallback(sprintf('umani/%s', $path));
    }

    /**
     * Redirect to external object reference.
     *
     * @param string $uname The uname of the entity.
     * @return \Cake\Http\Response
     */
    public function link(string $uname): Response
    {
        try {
            $object = (new ObjectsLoader())->loadObject($uname, 'links');
        } catch (RecordNotFoundException $e) {
            throw new NotFoundException('Missing url');
        }

        return $this->redirect($object->get('url'));
    }

    /**
     * Generic object view.
     *
     * @param string $path Object path.
     * @return \Cake\Http\Response
     */
    public function fallback(string $path): Response
    {
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
                // Check if the object is in the current publication's tree.
                $paths = $this->Publication->getViablePaths($object->id);
                if (empty($paths)) {
                    // the object isn't in the current publication. try to get its canonical URL
                    $url = $this->CanonicalUrl->buildCanonicalUrl($object);

                    if ($url !== null) {
                        return $this->redirect($url);
                    }

                    throw $e;
                }

                // Try to redirect to `/objects/{object}` to see if we can display it somehow.
                return $this->redirect(['_name' => 'pages:objects', 'uname' => $object->uname]);
            } catch (RecordNotFoundException $err) {
                // No object exists under this name. Re-throw original exception.
                throw $e;
            }
        }
    }
}
