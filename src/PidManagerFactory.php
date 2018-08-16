<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class PidManagerFactory
{

    public function __invoke(ContainerInterface $container) : PidManager
    {
        $pidFile = '/tmp/zend-swoole.pid';
        $logger = $container->has(LoggerInterface::class)
            ? $container->get(LoggerInterface::class)
            : null;
        return new PidManager($pidFile, $logger);
    }
}
