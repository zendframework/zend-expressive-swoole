<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;

interface MiddlewareInterface
{
    /**
     * @param string $filename The discovered filename being returned.
     * @param callable $next has the signature:
     *     function (Request $request, string $filename) : StaticResourceResponse
     */
    public function __invoke(
        Request $request,
        string $filename,
        callable $next
    ) : StaticResourceResponse;
}
