<?php
declare(strict_types=1);

namespace App\View\Helper;

use Chialab\FrontendKit\View\Helper\PlaceholdersHelper as BasePlaceholdersHelper;

/**
 * Extended Placeholders helper.
 */
class PlaceholdersHelper extends BasePlaceholdersHelper
{
    /**
     * Decode placeholder custom params (a JSON-encoded string) into an array.
     * Returns an empty array for non-string, empty or invalid input.
     *
     * @param mixed $params Placeholder custom params.
     * @return array<array-key, mixed>
     */
    public function params(mixed $params): array
    {
        if (!is_string($params) || $params === '') {
            return [];
        }

        $decoded = json_decode($params, true);

        return is_array($decoded) ? $decoded : [];
    }
}
