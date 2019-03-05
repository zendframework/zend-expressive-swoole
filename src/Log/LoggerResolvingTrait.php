<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Log;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

trait LoggerResolvingTrait
{
    private function getLogger(ContainerInterface $container) : LoggerInterface
    {
        return $container->has(SwooleLoggerFactory::SWOOLE_LOGGER)
            ? $container->get(SwooleLoggerFactory::SWOOLE_LOGGER)
            : (new SwooleLoggerFactory())($container);
    }
}
