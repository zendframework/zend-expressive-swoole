<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

class ConfigProvider
{
    public function __invoke() : array
    {
        return PHP_SAPI !== 'cli' || ! extension_loaded('swoole')
            ? []
            : ['dependencies' => $this->getDependencies()];
    }

    public function getDependencies() : array
    {
        return [
            'factories'  => [
                ServerRequestInterface::class => ServerRequestSwooleFactory::class,
                RequestHandlerRunner::class   => RequestHandlerSwooleRunnerFactory::class,
                SwooleHttpServer::class       => SwooleHttpServerFactory::class
            ]
        ];
    }
}
