<?php
declare(strict_types=1);

namespace Chialab\Controller;

use App\Controller\AppController as BaseController;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
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

        $locale = Configure::read('I18n.lang', I18n::getLocale());

        $this->loadComponent('Chialab/FrontendKit.Filters');
        $this->loadComponent('Chialab/FrontendKit.Categories');
        $this->loadComponent('Chialab/FrontendKit.Tags');
        $this->loadComponent('Chialab/FrontendKit.Objects', [
            'objectTypesConfig' => [
                'objects' => ['include' => 'poster|1'],
                'folders' => ['include' => 'poster|1,featured'],
            ],
            'autoHydrateAssociations' => [
                'featured' => 2,
                'poster' => 2,
            ],
        ]);
        $this->loadComponent('Chialab/FrontendKit.Menu');
        $this->loadComponent('Chialab/FrontendKit.Publication', [
            'publication' => Configure::read('RootFolder', 'chialab-design-company'),
            'publicationLoader' => [
                'objectTypesConfig' => [
                    'folders' => ['include' => 'poster|1'],
                    'profiles' => ['include' => 'poster|1'],
                    'links' => ['include' => 'poster|1'],
                ],
            ],
            'cache' => sprintf('publication_%s', $locale),
        ]);

        if (Configure::read('StagingSite')) {
            $this->loadComponent('Chialab/FrontendKit.Staging');
        }
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $analytics = Configure::read('Analytics', '');
        $locales = Configure::read('I18n.locales', []);
        $locale = Configure::read('I18n.lang', I18n::getLocale());
        $root = $this->Publication->getPublication();
        $menu = Cache::remember(
            sprintf('menu_%s', $locale),
            fn () => $this->Menu->load((string)$root->id),
        );

        $footerChildren = Cache::remember(sprintf('footer_%s', $locale), function () {
            $footerChildren = $this->Menu->load('footer')->children;
            foreach ($footerChildren as $key => $child) {
                if ($child->type === 'documents') {
                    $footerChildren[$key]->has_links = $this->Objects->loadRelatedObjects($child->uname, 'documents', 'has_links')->toArray();
                }
            }

            return $footerChildren;
        });

        $this->set(compact('menu', 'footerChildren', 'analytics', 'locale', 'locales'));
    }
}
