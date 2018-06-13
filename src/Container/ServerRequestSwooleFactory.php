<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Container;

use Psr\Container\ContainerInterface;
use swoole_http_request;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Return a factory for generating a server request from Swoole.
 */
class ServerRequestSwooleFactory
{
    public function __invoke(ContainerInterface $container) : callable
    {
        return function (swoole_http_request $request) {
            return ServerRequestFactory::fromSwoole($request);
        };
    }
}
