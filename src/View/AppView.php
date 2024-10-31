<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\View;

use Cake\Core\Configure;
use Chialab\FrontendKit\View\AppView as BaseAppView;

/**
 * Application View
 *
 * Your application's default view class
 *
 * @link https://book.cakephp.org/4/en/views.html#the-app-view
 */
class AppView extends BaseAppView
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading helpers.
     *
     * e.g. `$this->loadHelper('Html');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadHelper('Text');
        $this->loadHelper('Color');

        /** @todo Remove this line when `ThumbHelper` supports GIF images. */
        $this->helpers()->unload('Thumb');
        $this->loadHelper('Thumb');
        $this->loadHelper('VCard');

        /**
         * @var \Cake\View\Helper\PaginatorHelper $paginator
         */
        $paginator = $this->helpers()->get('Paginator');
        $paginator->setTemplates([
            'prevActive' => '<a rel="prev" class="link--section" href="{{url}}">{{text}}</a>',
            'nextActive' => '<a rel="next" class="link--section" href="{{url}}">{{text}}</a>',
        ]);

        if (Configure::check('FrontendPlugin')) {
            $this->setPlugin(Configure::read('FrontendPlugin'));
        }

        if (Configure::check('Theme')) {
            $this->setTheme(Configure::read('Theme'));
        } elseif (Configure::check('FrontendPlugin')) {
            $this->setTheme(Configure::read('FrontendPlugin'));
        }
    }
}
