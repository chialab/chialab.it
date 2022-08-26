<?php
namespace Design\Controller;

use Design\Controller\AppController;
use Chialab\FrontendKit\Traits\GenericActionsTrait;

/**
 * Pages Controller
 */
class PagesController extends AppController
{
    use GenericActionsTrait;

    /**
     * Load home objects.
     *
     * @return void
     */
    public function home(): void
    {
        $publication = $this->Publication->getPublication();
        $folders = $this->Menu->load($publication->id, null, null, 2)->children;

        $this->set(compact('folders'));
    }
}
