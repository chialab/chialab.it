<?php
declare(strict_types=1);

namespace Chialab\Middleware;

use Cake\Core\Configure;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RedirectUnknownLocaleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $request->getAttribute('params')['locale'];
        if (in_array($locale, Configure::read('I18n.locales', []), true)) {
            return $handler->handle($request);
        }

        $uri = $request->getUri();
        // The scope consumes the first two path segments: /lang/{locale}.
        $segments = explode('/', ltrim($uri->getPath(), '/'), 3);
        $target = $request->getAttribute('base') . '/' . ltrim($segments[2] ?? '', "/\\");
        if ($uri->getQuery() !== '') {
            $target .= '?' . $uri->getQuery();
        }

        return new RedirectResponse($target, 307);
    }
}
