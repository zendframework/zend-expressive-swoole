<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Container;

use Psr\Container\ContainerInterface;
use swoole_http_server;

class SwooleHttpServerFactory
{
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 8080;

    public function __invoke(ContainerInterface $container) : swoole_http_server
    {
        $config = $container->get('config');
        $host = $config['swoole']['host'] ?? static::DEFAULT_HOST;
        $port = $config['swoole']['port'] ?? static::DEFAULT_PORT;

        return new swoole_http_server($host, $port);
    }
}
