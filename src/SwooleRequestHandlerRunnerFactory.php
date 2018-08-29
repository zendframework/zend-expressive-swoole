<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;

class SwooleRequestHandlerRunnerFactory
{
    public function __invoke(ContainerInterface $container) : SwooleRequestHandlerRunner
    {
        $staticResourceHandler = $container->has(StaticResourceHandlerInterface::class)
            ? $container->get(StaticResourceHandlerInterface::class)
            : null;
        $logger = $container->has(LoggerInterface::class)
            ? $container->get(LoggerInterface::class)
            : null;

        return new SwooleRequestHandlerRunner(
            $container->get(ApplicationPipeline::class),
            $container->get(ServerRequestInterface::class),
            $container->get(ServerRequestErrorResponseGenerator::class),
            $container->get(PidManager::class),
            $container->get(ServerFactory::class),
            $staticResourceHandler,
            $logger
        );
    }
}
