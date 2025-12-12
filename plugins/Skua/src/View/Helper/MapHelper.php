<?php
declare(strict_types=1);

namespace Skua\View\Helper;

use Brick\Geo\Point;
use Cake\View\Helper;
use Chialab\Geometry\Geometry;

/**
 * Helper to render data for maps with geoJSON.
 */
class MapHelper extends Helper
{
    /**
     * Get a lat/lon string from a BEdita object
     *
     * @param object $object BEdita object
     * @return string|null lat/lon string
     */
    public function getObjectCoords(object|null $object): string|null
    {
        if (!$object || !isset($object['coords'])) {
            return null;
        }

        $geom = Geometry::parse($object['coords'])->getGeometry();

        if (!$geom instanceof Point) {
            return null;
        }

        return sprintf('%.15f,%.15f', $geom->y(), $geom->x());
    }

    /**
     * Build a geoJSON object from an object list.
     *
     * @param iterable $objects Objects list
     * @return array GeoJSON response
     */
    public function geoJSON(iterable $objects): array
    {
        $jsonObject = ['type' => 'FeatureCollection', 'features' => []];
        $lineCoords = [];

        foreach ($objects as $object) {
            if (empty($object['coords'])) {
                continue;
            }

            $coords = Geometry::parse($object['coords']);
            $geom = $coords->getGeometry();
            if ($geom instanceof Point) {
                $lineCoords[] = [$geom->x(), $geom->y()];
            }

            // le location senza titolo, descrizione e body servono solo per la lineString e non devono creare marker
            if (!$object['title'] && !$object['description'] && !$object['body']) {
                continue;
            }

            // $object['marker-symbol'] = 'marker-skua';
            $object['marker-symbol'] = 'marker-point';
            $jsonObject['features'][] = [
                'geometry' => $coords,
                'type' => 'Feature',
                'properties' => [
                    'marker-symbol' => 'marker-point',
                    // 'marker-symbol' => 'marker-skua',
                    // 'marker-anchor' => 'bottom',
                    'marker-anchor' => 'center',
                    'id' => $object['id'],
                    'uname' => $object['uname'],
                ],
            ];
        }

        $jsonObject['features'][] = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $lineCoords,
            ],
            'properties' => [
                'style' => [
                    'type' => 'line',
                    'line-color' => '#ff0000',
                    'line-width' => 1,
                ],
            ]
        ];

        return $jsonObject;
    }
}
