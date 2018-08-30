<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-logger for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-logger/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Log;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

interface AccessLogInterface extends LoggerInterface
{
    public function logAccessForStaticResource(Request $request, StaticResourceResponse $response) : void;

    public function logAccessForPsr7Resource(Request $request, ResponseInterface $response) : void;
}
