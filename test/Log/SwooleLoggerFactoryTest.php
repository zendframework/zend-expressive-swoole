<?php
declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Log;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Swoole\Log\StdoutLogger;
use Zend\Expressive\Swoole\Log\SwooleLoggerFactory;

class SwooleLoggerFactoryTest extends TestCase
{
    use LoggerFactoryHelperTrait;

    public function testReturnsConfiguredNamedLogger()
    {
        $logger = (new SwooleLoggerFactory())($this->createContainerMockWithNamedLogger());
        $this->assertSame($this->logger, $logger);
    }

    public function provideConfigsWithNoNamedLogger(): iterable
    {
        yield 'no config' => [null];
        yield 'empty config' => [[]];
        yield 'empty zend-expressive-swoole' => [['zend-expressive-swoole' => []]];
        yield 'empty swoole-http-server' => [['zend-expressive-swoole' => [
            'swoole-http-server' => [],
        ]]];
        yield 'empty logger' => [['zend-expressive-swoole' => [
            'swoole-http-server' => [
                'logger' => [],
            ],
        ]]];
    }

    /**
     * @dataProvider provideConfigsWithNoNamedLogger
     */
    public function testReturnsPsrLoggerWhenNoNamedLoggerIsFound(?array $config)
    {
        $logger = (new SwooleLoggerFactory())($this->createContainerMockWithConfigAndPsrLogger($config));
        $this->assertSame($this->logger, $logger);
    }

    /**
     * @dataProvider provideConfigsWithNoNamedLogger
     */
    public function testReturnsStdoutLoggerWhenOtherLoggersAreNotFound(?array $config)
    {
        $logger = (new SwooleLoggerFactory())($this->createContainerMockWithConfigAndNotPsrLogger($config));
        $this->assertInstanceOf(StdoutLogger::class, $logger);
    }
}
