<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Swoole\HotCodeReload\Reloader;
use Zend\Expressive\Swoole\Log\AccessLogInterface;
use Zend\Expressive\Swoole\Log\Psr3AccessLogDecorator;
use Zend\Expressive\Swoole\PidManager;
use Zend\Expressive\Swoole\ServerFactory;
use Zend\Expressive\Swoole\StaticResourceHandlerInterface;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunnerFactory;

class SwooleRequestHandlerRunnerFactoryTest extends TestCase
{
    protected function setUp() : void
    {
        $this->applicationPipeline = $this->prophesize(ApplicationPipeline::class);
        $this->applicationPipeline->willImplement(RequestHandlerInterface::class);

        $this->serverRequest = $this->prophesize(ServerRequestInterface::class);

        $this->serverRequestError = $this->prophesize(ServerRequestErrorResponseGenerator::class);
        $this->serverFactory = $this->prophesize(ServerFactory::class);
        $this->pidManager = $this->prophesize(PidManager::class);

        $this->staticResourceHandler = $this->prophesize(StaticResourceHandlerInterface::class);
        $this->logger = $this->prophesize(AccessLogInterface::class);
        $this->hotCodeReloader = $this->prophesize(Reloader::class);

        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container
            ->get(ApplicationPipeline::class)
            ->will([$this->applicationPipeline, 'reveal']);
        $this->container
            ->get(ServerRequestInterface::class)
            ->willReturn(function () {
                return $this->serverRequest->reveal();
            });
        $this->container
            ->get(ServerRequestErrorResponseGenerator::class)
            ->willReturn(function () {
                $this->serverRequestError->reveal();
            });
        $this->container
            ->get(PidManager::class)
            ->will([$this->pidManager, 'reveal']);

        $this->container
            ->get(SwooleHttpServer::class)
            ->willReturn($this->createMock(SwooleHttpServer::class));
    }

    public function configureAbsentStaticResourceHandler()
    {
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(false);

        $this->container
            ->get(StaticResourceHandlerInterface::class)
            ->shouldNotBeCalled();

        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'static-files' => [],
                    ],
                ],
            ]);
    }

    public function configureAbsentLoggerService()
    {
        $this->container
            ->has(AccessLogInterface::class)
            ->willReturn(false);

        $this->container
            ->get(AccessLogInterface::class)
            ->shouldNotBeCalled();
    }

    public function configureAbsentConfiguration() : void
    {
        $this->container
            ->has('config')
            ->willReturn(false);

        $this->container
            ->get('config')
            ->shouldNotBeCalled();
    }

    public function configureAbsentHotCodeReloader() : void
    {
        $this->container
            ->has(Reloader::class)
            ->willReturn(false);

        $this->container
            ->get(Reloader::class)
            ->shouldNotBeCalled();
    }

    public function testInvocationWithoutOptionalServicesConfiguresInstanceWithDefaults()
    {
        $this->configureAbsentStaticResourceHandler();
        $this->configureAbsentLoggerService();
        $this->configureAbsentConfiguration();
        $this->configureAbsentHotCodeReloader();
        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeEmpty('staticResourceHandler', $runner);
        $this->assertAttributeInstanceOf(Psr3AccessLogDecorator::class, 'logger', $runner);
    }

    public function testFactoryWillUseConfiguredPsr3LoggerWhenPresent()
    {
        $this->configureAbsentStaticResourceHandler();
        $this->configureAbsentConfiguration();
        $this->configureAbsentHotCodeReloader();
        $this->container
            ->has(AccessLogInterface::class)
            ->willReturn(true);
        $this->container
            ->get(AccessLogInterface::class)
            ->will([$this->logger, 'reveal']);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeSame($this->logger->reveal(), 'logger', $runner);
    }

    public function testFactoryWillUseConfiguredStaticResourceHandlerWhenPresent()
    {
        $this->configureAbsentLoggerService();
        $this->configureAbsentHotCodeReloader();
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(true);
        $this->container
            ->get(StaticResourceHandlerInterface::class)
            ->will([$this->staticResourceHandler, 'reveal']);
        $this->container->has('config')->willReturn(true);
        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'static-files' => [
                            'enable' => true,
                        ],
                    ],
                ],
            ]);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeSame($this->staticResourceHandler->reveal(), 'staticResourceHandler', $runner);

        return $runner;
    }

    public function testFactoryWillIgnoreConfiguredStaticResourceHandlerWhenStaticFilesAreDisabled()
    {
        $this->configureAbsentLoggerService();
        $this->configureAbsentHotCodeReloader();
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(true);
        $this->container->has('config')->willReturn(true);
        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'static-files' => [
                            'enable' => false, // Disabling static files
                        ],
                    ],
                ],
            ]);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());

        $this->container
            ->get(StaticResourceHandlerInterface::class)
            ->shouldNotHaveBeenCalled();
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeEmpty('staticResourceHandler', $runner);
    }

    /**
     * @depends testFactoryWillUseConfiguredStaticResourceHandlerWhenPresent
     */
    public function testFactoryUsesDefaultProcessNameIfNoneProvidedInConfiguration(SwooleRequestHandlerRunner $runner)
    {
        $this->assertAttributeSame(SwooleRequestHandlerRunner::DEFAULT_PROCESS_NAME, 'processName', $runner);
    }

    public function testFactoryUsesConfiguredProcessNameWhenPresent()
    {
        $this->configureAbsentLoggerService();
        $this->configureAbsentHotCodeReloader();
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(false);
        $this->container->has('config')->willReturn(true);
        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'process-name' => 'zend-expressive-swoole-test',
                    ],
                ],
            ]);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());

        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeSame('zend-expressive-swoole-test', 'processName', $runner);
    }

    public function testFactoryWillUseConfiguredHotCodeReloaderWhenPresent()
    {
        $this->configureAbsentLoggerService();
        $this->container->has(Reloader::class)->willReturn(true);
        $this->container
            ->get(Reloader::class)
            ->will([$this->hotCodeReloader, 'reveal']);
        $this->container->has('config')->willReturn(true);
        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'hot-code-reload' => [
                        'enable' => true,
                    ],
                ],
            ]);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());

        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeSame($this->hotCodeReloader->reveal(), 'hotCodeReloader', $runner);
    }
}
