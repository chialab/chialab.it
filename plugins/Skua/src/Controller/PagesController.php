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
     * Load home objects.
     *
     * @return void
     */
    public function home(): void
    {
        if (empty($this->journeys)) {
            throw new Exception('No journeys found');
        }

        // rimando al primo viaggio dentro la root folder
        $firstJourney = $this->journeys[0];
        $this->redirect(['_name' => 'pages:journey', 'uname' => $firstJourney->uname]);
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
        $loader = new ObjectsLoader();
        $journey = $loader->loadObject($uname, 'folders');
        if (!$journey) {
            throw new RecordNotFoundException(sprintf("Journey folder %s not found", $uname));
        }

        $children = $loader->loadObjects(
            ['parent' => $journey->uname],
            'locations',
            ['include' => 'has_media'],
            ['has_media' => 2]
        );
        $this->set('mapboxToken', Configure::read('Maps.mapbox.token'));
        $this->viewBuilder()->addHelpers(['Skua.Map']);
        $this->set(compact('children'));
        $this->set('currentJourney', $journey);
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

        // call live tracking
        $http = new Client();
        $response = $http->get('https://skua.le0m.net?ship=8178410', [], [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic bGUwbTpQYjdrVHFWamdZdWdnUDlIcmo3dmpuOUg='
            ]
        ]);
        $response = $response->getJson(); // ['latitude' => ..., 'longitude' => ...]
        $center = sprintf('%.15f,%.15f', $response['latitude'], $response['longitude']);

        $data = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$response['longitude'], $response['latitude']],
                    ],
                    'properties' => [
                        'marker-symbol' => 'marker-skua',
                        'marker-anchor' => 'bottom',
                    ],
                ],
            ],
        ];

        $this->set(compact('data', 'center'));
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
