<?php
declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Log;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Zend\Expressive\Swoole\Log\StdoutLogger;
use Zend\Expressive\Swoole\Log\SwooleLoggerFactory;

class SwooleLoggerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class)->reveal();
    }

    public function testReturnsConfiguredNamedLogger()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'logger' => [
                        'logger-name' => 'my_logger',
                    ],
                ],
            ],
        ]);
        $this->container->get('my_logger')->willReturn($this->logger);

        $logger = (new SwooleLoggerFactory())($this->container->reveal());

        $this->assertSame($this->logger, $logger);
    }

    public function provideConfigsWithNoNamedLogger(): iterable
    {
        yield 'no config' => [false, []];
        yield 'empty config' => [true, []];
        yield 'empty zend-expressive-swoole' => [true, ['zend-expressive-swoole' => []]];
        yield 'empty swoole-http-server' => [true, ['zend-expressive-swoole' => [
            'swoole-http-server' => [],
        ]]];
        yield 'empty logger' => [true, ['zend-expressive-swoole' => [
            'swoole-http-server' => [
                'logger' => [],
            ],
        ]]];
    }

    /**
     * @dataProvider provideConfigsWithNoNamedLogger
     */
    public function testReturnsPsrLoggerWhenNoNamedLoggerIsFound(bool $hasConfig, array $config)
    {
        $this->container->has('config')->willReturn($hasConfig);
        $this->container->get('config')->willReturn($config);
        $this->container->has(LoggerInterface::class)->willReturn(true);
        $this->container->get(LoggerInterface::class)->willReturn($this->logger);

        $logger = (new SwooleLoggerFactory())($this->container->reveal());

        $this->assertSame($this->logger, $logger);
    }

    /**
     * @dataProvider provideConfigsWithNoNamedLogger
     */
    public function testReturnsStdoutLoggerWhenOtherLoggersAreNotFound(bool $hasConfig, array $config)
    {
        $this->container->has('config')->willReturn($hasConfig);
        $this->container->get('config')->willReturn($config);
        $this->container->has(LoggerInterface::class)->willReturn(false);

        $logger = (new SwooleLoggerFactory())($this->container->reveal());

        $this->assertInstanceOf(StdoutLogger::class, $logger);
    }
}
