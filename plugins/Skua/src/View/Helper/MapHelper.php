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

        return implode(',', [number_format($geom->y(), 6, '.', ''), number_format($geom->x(), 6, '.', '')]);
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

            $object['marker-symbol'] = 'marker-skua';
            $coords = Geometry::parse($object['coords']);
            $geometry = json_encode($coords);
            $jsonObject['features'][] = [
                'geometry' => json_decode($geometry, true),
                'geometry' => $coords,
                'type' => 'Feature',
                'properties' => [
                    'marker-symbol' => 'marker-skua',
                    'marker-anchor' => 'bottom',
                ],
            ];
            $geom = $coords->getGeometry();
            if ($geom instanceof Point) {
                $lineCoords[] = [$geom->x(), $geom->y()];
            }
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
                    'line-width' => 2,
                    'line-dasharray' => [8, 8],
                ],
            ]
        ];

        return $jsonObject;
    }
}
