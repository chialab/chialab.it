<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Response;

/**
 * Staging Controller
 */
class StagingController extends AppController
{
    /**
     * Clear application cache.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\ForbiddenException When clearing cache is not allowed.
     */
    public function clearCache(): Response
    {
        if (!Configure::read('StagingSite')) {
            throw new ForbiddenException('Clearing cache is allowed only on staging site.');
        }
        if (!$this->Staging->isAuthRequired()) {
            throw new ForbiddenException('Clearing cache is allowed only when authentication is required.');
        }

        $this->request->allowMethod(['post']);
        Cache::clear('_clear_cache_');

        return $this->redirect($this->referer('/'));
    }
}
