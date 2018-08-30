<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;

interface StaticResourceHandlerInterface
{
    /**
     * Attempt to process a static resource based on the current request.
     *
     * If the resource cannot be processed, the method should return null.
     * Otherwise, it should return the StaticResourceResponse that was used
     * to send the Swoole response instance. The runner can then query this
     * for content length and status.
     */
    public function processStaticResource(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) : ?StaticResourceHandler\StaticResourceResponse;
}
