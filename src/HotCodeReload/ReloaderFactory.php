<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\HotCodeReload;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Zend\Expressive\Swoole\Log\StdoutLogger;

class ReloaderFactory
{
    public function __invoke(ContainerInterface $container) : Reloader
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $swooleConfig = $config['zend-expressive-swoole'] ?? [];
        $hotCodeReloadConfig = $swooleConfig['hot-code-reload'] ?? [];

        return new Reloader(
            $container->get(FileWatcherInterface::class),
            $this->getLogger($container),
            $hotCodeReloadConfig['interval'] ?? 500
        );
    }

    private function getLogger(ContainerInterface $container) : LoggerInterface
    {
        return $container->has(LoggerInterface::class)
            ? $container->get(LoggerInterface::class)
            : new StdoutLogger();
    }
}
