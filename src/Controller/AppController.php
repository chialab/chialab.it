<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;

/**
 * Application Controller
 *
 * @property \Chialab\FrontendKit\Controller\Component\FiltersComponent $Filters
 * @property \Chialab\FrontendKit\Controller\Component\CategoriesComponent $Categories
 * @property \Chialab\FrontendKit\Controller\Component\TagsComponent $Tags
 * @property \Chialab\FrontendKit\Controller\Component\ObjectsComponent $Objects
 * @property \Chialab\FrontendKit\Controller\Component\PublicationComponent $Publication
 * @property \Chialab\FrontendKit\Controller\Component\MenuComponent $Menu
 * @property \Chialab\FrontendKit\Controller\Component\StagingComponent $Staging
 */
class AppController extends Controller
{
    /**
     * Name of root folder.
     *
     * @var string
     */
    protected const ROOT_FOLDER = 'root';

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler', [
            'enableBeforeRedirect' => false,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->set('debug', Configure::read('debug', false));
    }
}
