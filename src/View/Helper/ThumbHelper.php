<?php
declare(strict_types=1);

namespace App\View\Helper;

use BEdita\Core\Model\Entity\ObjectEntity;
use Chialab\FrontendKit\View\Helper\ThumbHelper as BaseThumbHelper;

/**
 * Extended Thumb helper.
 * Workaround for GIF images.
 */
class ThumbHelper extends BaseThumbHelper
{
    /**
     * {@inheritDoc}
     */
    public function url(ObjectEntity|null $object, array|string $thumbOptions = 'default', array $fallbackOptions = []): string|null
    {
        if ($this->Media->isMedia($object) && $this->Media->getStream($object)?->mime_type === 'image/gif') {
            return $object->get('media_url');
        }

        return parent::url($object, $thumbOptions, $fallbackOptions);
    }
}
