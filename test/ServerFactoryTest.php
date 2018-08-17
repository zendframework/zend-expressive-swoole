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
use Swoole\Process;
use Zend\Expressive\Swoole\ServerFactory;

class ServerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->serverFactory = new ServerFactory();
    }

    public function testInvokeWithoutConfig()
    {
        $process = new Process(function ($worker) {
            $server = ($this->serverFactory)($this->container->reveal());
            $swooleServer = $server->createSwooleServer();
            $worker->write(sprintf('%s:%d', $swooleServer->host, $swooleServer->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame(
            sprintf('%s:%d', ServerFactory::DEFAULT_HOST, ServerFactory::DEFAULT_PORT),
            $data
        );
    }

    public function testInvokeWithConfig()
    {
        $config = [
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'host' => 'localhost',
                    'port' => 9501,
                ]
            ]
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function ($worker) {
            $server = ($this->serverFactory)($this->container->reveal());
            $swooleServer = $server->createSwooleServer();
            $worker->write(sprintf('%s:%d', $swooleServer->host, $swooleServer->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('localhost:9501', $data);
    }

    public function testInvokeWithOptions()
    {
        $host = 'localhost';
        $port = 9501;
        $options = [
            'daemonize' => false,
            'worker_num' => 1,
            'dispatch_mode' => 3,
        ];
        $config = [
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'host' => $host,
                    'port' => $port,
                    'options' => $options,
                ]
            ]
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function ($worker) {
            $server = ($this->serverFactory)($this->container->reveal());
            $swooleServer = $server->createSwooleServer();
            $worker->write(serialize([
                'host' => $swooleServer->host,
                'port' => $swooleServer->port,
                'options' => $swooleServer->setting,
            ]));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = unserialize($process->read());
        Process::wait(true);

        $this->assertSame([
            'host' => $host,
            'port' => $port,
            'options' => $options,
        ], $data);
    }
}
