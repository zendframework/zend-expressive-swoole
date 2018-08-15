<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;

class ServerFactory
{

    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $swooleConfig = $config['zend-expressive-swoole']['swoole-http-server'] ?? null;
        $host = $swooleConfig['host'] ?? static::DEFAULT_HOST;
        $port = $swooleConfig['port'] ?? static::DEFAULT_PORT;
        $mode = $swooleConfig['mode'] ?? SWOOLE_BASE;
        $protocol = $swooleConfig['protocol'] ?? SWOOLE_SOCK_TCP;
        $options = $swooleConfig['options'] ?? [];
        $server = new Server($host, $port, $mode, $protocol, $options);
        return $server;
    }

}