<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Swoole\PidManager;

class StatusCommandFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new StatusCommand($container->get(PidManager::class));
    }
}
