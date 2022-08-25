<?php

namespace Design;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for Chialab
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritdoc
     */
    public function bootstrap(PluginApplicationInterface $app)
    {
        parent::bootstrap($app);

        Configure::write('ObjectsLoader', []);
        Configure::write('Publication', [
            'publication' => 'chialab-design-company',
        ]);
        Configure::write('Menu', [
            'folders' => [],
        ]);
        // Configure::write('Analytics', [
        //     'ga_code' => '',
        //     'matomo_code' => '',
        // ]);
    }

    /**
     * @inheritDoc
     */
    public function getTemplatePath(): string
    {
        if ($this->templatePath) {
            return $this->templatePath;
        }

        return $this->templatePath = $this->getPath() . 'src' . DS . 'Template';
    }
}
