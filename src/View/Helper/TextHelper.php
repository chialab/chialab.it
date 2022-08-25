<?php
namespace App\View\Helper;

use Cake\View\Helper;

/**
 * Text helper
 */
class TextHelper extends Helper
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    /**
     * Render a text removing block tags and links.
     * @param string $text The original text to render.
     * @return string
     */
    public function renderDescription(string $text): string
    {
        return strip_tags($text, ['strong', 'em', 'ul', 'li', 'u', 'b', 'i']);
    }

}
