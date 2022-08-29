<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\I18n\I18n;

class AppController extends Controller
{
    /**
     * @inheritDoc
     */
    public function beforeRender(Event $event): ?Response
    {
        parent::beforeRender($event);

        $locales = Configure::read('I18n.locales', []);
        $locale = Configure::read('I18n.lang', I18n::getLocale());
        $this->set(compact('locale', 'locales'));

        return null;
    }
}
