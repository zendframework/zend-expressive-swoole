<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;
use Whoops\Handler\PrettyPageHandler;

class WhoopsPrettyPageHandlerDelegator
{
    public function __invoke(ContainerInterface $container, $serviceName, callable $callback) : PrettyPageHandler
    {
        /** @var PrettyPageHandler $pageHandler */
        $pageHandler = $callback();
        $pageHandler->handleUnconditionally(true);
        return $pageHandler;
    }
}
