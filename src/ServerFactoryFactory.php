<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;

class ServerFactoryFactory
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 8080;

    public function __invoke(ContainerInterface $container) : ServerFactory
    {
        $config = $container->get('config');
        $swooleConfig = $config['zend-expressive-swoole']['swoole-http-server'] ?? [];

        return new ServerFactory(
            $swooleConfig['host'] ?? static::DEFAULT_HOST,
            $swooleConfig['port'] ?? static::DEFAULT_PORT,
            $swooleConfig['mode'] ?? SWOOLE_BASE,
            $swooleConfig['protocol'] ?? SWOOLE_SOCK_TCP,
            $swooleConfig['options'] ?? []
        );
    }
}
