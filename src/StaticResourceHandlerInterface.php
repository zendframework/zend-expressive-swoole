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
     * Does the request match to a static resource?
     */
    public function isStaticResource(SwooleHttpRequest $request) : bool;

    /**
     * Send the static resource based on the current request.
     */
    public function sendStaticResource(SwooleHttpRequest $request, SwooleHttpResponse $response) : void;
}
