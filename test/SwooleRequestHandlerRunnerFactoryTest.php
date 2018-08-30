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
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Swoole\Exception\InvalidConfigException;
use Zend\Expressive\Swoole\PidManager;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunnerFactory;
use Zend\Expressive\Swoole\ServerFactory;
use Zend\Expressive\Swoole\StaticResourceHandlerInterface;
use Zend\Expressive\Swoole\Log\AccessLogInterface;
use Zend\Expressive\Swoole\Log\Psr3AccessLogDecorator;
use Zend\Expressive\Swoole\Log\StdoutLogger;

class SwooleRequestHandlerRunnerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->applicationPipeline = $this->prophesize(ApplicationPipeline::class);
        $this->applicationPipeline->willImplement(RequestHandlerInterface::class);

        $this->serverRequest = $this->prophesize(ServerRequestInterface::class);

        $this->serverRequestError = $this->prophesize(ServerRequestErrorResponseGenerator::class);
        $this->serverFactory = $this->prophesize(ServerFactory::class);
        $this->pidManager = $this->prophesize(PidManager::class);

        $this->staticResourceHandler = $this->prophesize(StaticResourceHandlerInterface::class);
        $this->logger = $this->prophesize(AccessLogInterface::class);

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
            ->get(ServerFactory::class)
            ->will([$this->serverFactory, 'reveal']);
    }

    public function configureAbsentStaticResourceHandler()
    {
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(false);

        $this->container
            ->get(StaticResourceHandlerInterface::class)
            ->shouldNotBeCalled();
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

    public function testInvocationWithoutOptionalServicesConfiguresInstanceWithDefaults()
    {
        $this->configureAbsentStaticResourceHandler();
        $this->configureAbsentLoggerService();
        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeEmpty('staticResourceHandler', $runner);
        $this->assertAttributeInstanceOf(Psr3AccessLogDecorator::class, 'logger', $runner);
    }

    public function testFactoryWillUseConfiguredPsr3LoggerWhenPresent()
    {
        $this->configureAbsentStaticResourceHandler();
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
        $this->container
            ->has(StaticResourceHandlerInterface::class)
            ->willReturn(true);
        $this->container
            ->get(StaticResourceHandlerInterface::class)
            ->will([$this->staticResourceHandler, 'reveal']);

        $factory = new SwooleRequestHandlerRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $runner);
        $this->assertAttributeSame($this->staticResourceHandler->reveal(), 'staticResourceHandler', $runner);
    }
}
