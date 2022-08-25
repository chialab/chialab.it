<?php

namespace Design\Controller;

use App\Controller\AppController as BaseController;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Response;

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
        $this->loadComponent('RequestHandler', [
            'enableBeforeRedirect' => false,
        ]);
        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP security settings.
         * see https://book.cakephp.org/3/en/controllers/components/security.html
         */
        //$this->loadComponent('Security');

        $this->loadComponent('Chialab/FrontendKit.Filters');
        $this->loadComponent('Chialab/FrontendKit.Categories');
        $this->loadComponent('Chialab/FrontendKit.Tags');
        $this->loadComponent('Chialab/FrontendKit.Objects', Configure::read('ObjectsLoader', []));
        $this->loadComponent('Chialab/FrontendKit.Menu');
        $this->loadComponent('Chialab/FrontendKit.Publication', Configure::read('Publication', []));

        $isStaging = Configure::read('StagingSite');
        $this->set('isStaging', $isStaging);
        if ($isStaging) {
            $this->loadComponent('Authentication.Authentication');
        }
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(Event $event): ?Response
    {
        parent::beforeRender($event);

        $publication = $this->Publication->getPublication();
        $menu = $this->Menu->load($publication->id);
        $analytics = Configure::read('Analytics', '');

        $this->set(compact('menu', 'analytics'));

        return null;
    }
}
