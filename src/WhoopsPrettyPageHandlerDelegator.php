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

/**
 * Configure Whoops to work under Swoole.
 *
 * The PrettyPageHandler of Whoops is configured by default to abort when it
 * detects it is under a CLI SAPI - which is what Swoole runs under. You can
 * force it to continue handling an error by toggling the * "handleUnconditionally"
 * flag.
 */
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
