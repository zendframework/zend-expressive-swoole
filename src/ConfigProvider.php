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
use function extension_loaded;

class ConfigProvider
{
    public function __invoke() : array
    {
        $config = PHP_SAPI === 'cli' && extension_loaded('swoole')
            ? ['dependencies' => $this->getDependencies()]
            : [];

        $config['zend-expressive-swoole'] = $this->getDefaultConfig();

        return $config;
    }

    public function getDefaultConfig() : array
    {
        return [
            'swoole-http-server' => [
                'options' => [
                    // We set a default for this. Without one, Swoole\Http\Server
                    // defaults to the value of `ulimit -n`. Unfortunately, in
                    // virtualized or containerized environments, this often
                    // reports higher than the host container allows. 1024 is a
                    // sane default; users should check their host system, however,
                    // and set a production value to match.
                    'max_conn' => 1024,
                ],
            ],
        ];
    }

    public function getDependencies() : array
    {
        return [
            'factories'  => [
                Log\AccessLogInterface::class         => Log\AccessLogFactory::class,
                PidManager::class                     => PidManagerFactory::class,
                SwooleRequestHandlerRunner::class     => SwooleRequestHandlerRunnerFactory::class,
                ServerRequestInterface::class         => ServerRequestSwooleFactory::class,
                StaticResourceHandlerInterface::class => StaticResourceHandlerFactory::class,
                SwooleHttpServer::class               => HttpServerFactory::class,
            ],
            'aliases' => [
                RequestHandlerRunner::class           => SwooleRequestHandlerRunner::class,
            ],
        ];
    }
}
