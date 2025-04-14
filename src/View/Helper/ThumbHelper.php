<?php
declare(strict_types=1);

namespace App\View\Helper;

use BEdita\Core\Model\Entity\Media;
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
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity|null $object Object entity.
     * @param array<array-key, mixed>|string $thumbOptions Thumbnail options.
     * @param array{allowPending?: bool, fallbackOriginal?: bool, fallbackStatic?: bool} $fallbackOptions Fallback options.
     */
    public function url(ObjectEntity|null $object, array|string $thumbOptions = 'default', array $fallbackOptions = []): string|null
    {
        if (
            $object instanceof Media &&
            $this->Media->getStream($object)?->mime_type === 'image/gif'
        ) {
            return $object->get('media_url');
        }

        return parent::url($object, $thumbOptions, $fallbackOptions);
    }
}
