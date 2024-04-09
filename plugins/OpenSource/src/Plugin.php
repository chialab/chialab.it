<?php

namespace OpenSource;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for OpenSource
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritdoc
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
