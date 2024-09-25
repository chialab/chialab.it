<?php
declare(strict_types=1);

namespace Chialab;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for Chialab websites.
 */
class ChialabPlugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        Configure::write('ObjectsLoader', [
            'objectTypesConfig' => [
                'objects' => ['include' => 'poster'],
                'documents' => ['include' => 'poster,has_media,has_clients,see_also,has_links'],
                'news' => ['include' => 'poster,has_media,see_also'],
                'folders' => ['include' => 'children,poster'],
            ],
        ]);
    }
}
