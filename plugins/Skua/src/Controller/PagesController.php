<?php
declare(strict_types=1);

namespace Skua\Controller;

use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Client;
use Cake\Http\Response;
use Chialab\FrontendKit\Model\ObjectsLoader;
use Chialab\FrontendKit\Traits\GenericActionsTrait;
use Exception;

/**
 * Pages Controller
 */
class PagesController extends AppController
{
    use GenericActionsTrait {
        fallback as private _fallback;
    }

    /**
     * Live SKUA tracking page.
     *
     * @return void
     */
    public function tracking(): void
    {
        $this->set('mapboxToken', Configure::read('Maps.mapbox.token'));
        $this->viewBuilder()->addHelpers(['Skua.Map']);

        try {
            $response = (new Client())->get(Configure::read('Skua.trackingUrl'), [], ['timeout' => 5]);
            $route = $response->getJson()['route'] ?? [];
        } catch (Exception $e) {
            return;
        }

        $last = null;
        foreach ($route as $point) {
            if (isset($point['p']['lat'], $point['p']['lon'])) {
                $last = [$point['p']['lon'], $point['p']['lat']];
            }
        }
        if ($last === null) {
            return;
        }

        $center = sprintf('%.15f,%.15f', $last[1], $last[0]);
        $features = [
            [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => $last],
                'properties' => ['marker-symbol' => 'marker-skua', 'marker-anchor' => 'bottom'],
            ],
        ];
        $data = ['type' => 'FeatureCollection', 'features' => $features];
        $this->set(compact('data', 'center'));
    }

    /**
     * Homepage redirecting to the latest journey.
     *
     * @return void
     */
    public function home(): void
    {
        if (empty($this->journeys)) {
            throw new Exception('No journeys found');
        }

        // rimando all'ultimo viaggio dentro la root folder
        $lastJourney = collection($this->journeys)->last();
        $this->redirect(['_name' => 'pages:journey', 'uname' => $lastJourney->uname]);
    }

    /**
     * Journey folder page.
     *
     * @param string $uname Journey folder uname.
     * @return void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When journey is not found.
     */
    public function journey(string $uname): void
    {
        $loader = new ObjectsLoader(['galleries' => ['include' => 'has_media']], ['has_media' => 3]);
        $journey = $loader->loadObject($uname, 'folders');
        if (!$journey) {
            throw new RecordNotFoundException(sprintf('Journey folder %s not found', $uname));
        }

        $children = $loader->loadObjects(
            ['parent' => $journey->uname],
            'locations',
            ['include' => 'placeholder'],
            ['placeholder' => 2],
        );
        $this->set('mapboxToken', Configure::read('Maps.mapbox.token'));
        $this->viewBuilder()->addHelpers(['Skua.Map']);
        $this->set(compact('children'));
        $this->set('currentJourney', $journey);
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
                // Try to redirect to `/objects/{object}` to see if we can display it somehow.
                return $this->redirect(['_name' => 'pages:objects', 'uname' => $object->uname]);
            } catch (RecordNotFoundException $err) {
                // No object exists under this name. Re-throw original exception.
                throw $e;
            }
        }
    }
}
