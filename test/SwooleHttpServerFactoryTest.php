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
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Zend\Expressive\Swoole\SwooleHttpServerFactory;

class SwooleHttpServerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->swooleFactory = new SwooleHttpServerFactory();
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(SwooleHttpServerFactory::class, $this->swooleFactory);
    }

    public function testInvokeWithoutConfig()
    {
        $process = new Process(function ($worker) {
            $server = ($this->swooleFactory)($this->container->reveal());
            $worker->write(sprintf('%s:%d', $server->host, $server->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame(
            sprintf('%s:%d', SwooleHttpServerFactory::DEFAULT_HOST, SwooleHttpServerFactory::DEFAULT_PORT),
            $data
        );
    }

    public function testInvokeWithConfig()
    {
        $config = [
            'swoole_http_server' => [
                'host' => 'localhost',
                'port' => 9501,
            ],
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function ($worker) {
            $server = ($this->swooleFactory)($this->container->reveal());
            $worker->write(sprintf('%s:%d', $server->host, $server->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('localhost:9501', $data);
    }
}
