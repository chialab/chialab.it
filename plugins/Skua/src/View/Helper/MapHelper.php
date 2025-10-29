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
     * Get a lat/lon string from a location object
     *
     * @param object $location location object
     * @return string|null lat/lon string
     */
    public function getLocationCoords(object|null $location): string|null
    {
        if (!$location || !isset($location['coords'])) {
            return null;
        }

        $geom = Geometry::parse($location['coords'])->getGeometry();

        if (!$geom instanceof Point) {
            return null;
        }

        return implode(',', [number_format($geom->y(), 6, '.', ''), number_format($geom->x(), 6, '.', '')]);
    }
}
