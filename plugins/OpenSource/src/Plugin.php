<?php
declare(strict_types=1);

namespace OpenSource;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for OpenSource
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // Configure::write('Analytics', [
        //     'ga_code' => '',
        //     'matomo_code' => '',
        // ]);
    }
}
