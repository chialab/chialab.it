<?php
namespace App\View\Helper;

use Cake\View\Helper;

/**
 * Color helper
 */
class ColorHelper extends Helper
{
    /**
     * Convert a hex color to a rgb array.
     * Example: '#d60000' -> [r => 214, g => 0, b => 0]
     *
     * @param string $hex The hex color code.
     * @return array
     */
    public function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        $length = strlen($hex);
        $rgb = [
            'r' => hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0)),
            'g' => hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0)),
            'b' => hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0)),
        ];

        return $rgb;
    }
}
