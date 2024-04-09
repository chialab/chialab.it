<?php

namespace Design\Controller;

use App\Controller\AppController as BaseController;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\I18n;

/**
 * App Controller.
 *
 * @property \Chialab\FrontendKit\Controller\Component\FiltersComponent $Filters
 * @property \Chialab\FrontendKit\Controller\Component\CategoriesComponent $Categories
 * @property \Chialab\FrontendKit\Controller\Component\TagsComponent $Tags
 * @property \Chialab\FrontendKit\Controller\Component\ObjectsComponent $Objects
 * @property \Chialab\FrontendKit\Controller\Component\MenuComponent $Menu
 * @property \Chialab\FrontendKit\Controller\Component\PublicationComponent $Publication
 * @property \Authentication\Controller\Component\AuthenticationComponent|null $Authentication
 */
class AppController extends BaseController
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('Security');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Paginator');

        $this->loadComponent('Chialab/FrontendKit.Filters');
        $this->loadComponent('Chialab/FrontendKit.Categories');
        $this->loadComponent('Chialab/FrontendKit.Tags');
        $this->loadComponent('Chialab/FrontendKit.Objects', [
            'objectTypesConfig' => [
                'objects' => ['include' => 'poster'],
                'documents' => ['include' => 'poster,has_clients'],
            ],
        ]);
        $this->loadComponent('Chialab/FrontendKit.Menu');
        $this->loadComponent('Chialab/FrontendKit.Publication', [
            'publication' => 'chialab-design-company',
            'publicationLoader' => [
                'objectTypesConfig' => [
                    'profiles' => ['include' => 'poster|1'],
                    'links' => ['include' => 'poster|1'],
                    'publications' => ['include' => 'poster|1'],
                ],
            ],
        ]);

        if (Configure::read('StagingSite')) {
            $this->loadComponent('Chialab/FrontendKit.Staging');
        }
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(EventInterface $event): ?Response
    {
        parent::beforeRender($event);

        $root = $this->Publication->getPublication();
        $menu = $this->Menu->load($root->id);
        $footer = $this->Menu->load('chialab-design-company-footer')->children;
        $analytics = Configure::read('Analytics', '');
        $locales = Configure::read('I18n.locales', []);
        $locale = Configure::read('I18n.lang', I18n::getLocale());

        $this->set(compact('menu', 'footer', 'analytics', 'locale', 'locales'));

        return null;
    }
}
