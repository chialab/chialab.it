<?php
declare(strict_types=1);

namespace Chialab;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for Chialab websites.
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
