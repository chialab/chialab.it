<?php
declare(strict_types=1);

namespace Chialab\Controller;

use Cake\Event\EventInterface;

/**
 * Error controller.
 */
class ErrorController extends AppController
{
    /**
     * {@inheritDoc}
     *
     * @param \Cake\Event\EventInterface<\Chialab\Controller\AppController> $event Dispatched event.
     */
    public function beforeFilter(EventInterface $event): void
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param \Cake\Event\EventInterface<\Chialab\Controller\AppController> $event Dispatched event.
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->viewBuilder()->setTemplatePath('Error');
    }

    /**
     * {@inheritDoc}
     *
     * @param \Cake\Event\EventInterface<\Chialab\Controller\AppController> $event Dispatched event.
     */
    public function afterFilter(EventInterface $event): void
    {
    }
}
