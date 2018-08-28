<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;

use function in_array;

class MethodNotAllowedMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, string $filename, callable $next) : StaticResourceResponse
    {
        $server = $request->server;
        if (in_array($server['request_method'], ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request, $filename);
        }

        return new StaticResourceResponse(
            405,
            ['Allow' => 'GET, HEAD, OPTIONS'],
            false
        );
    }
}
