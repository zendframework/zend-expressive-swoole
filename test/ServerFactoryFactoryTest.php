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
use Zend\Expressive\Swoole\ServerFactoryFactory;

class ServerFactoryFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new ServerFactoryFactory();
    }

    public function testFactoryCreatesInstanceUsingDefaultsWhenNoConfigIsAvailable()
    {
        $process = new Process(function ($worker) {
            $server = ($this->factory)($this->container->reveal());
            $swooleServer = $server->createSwooleServer();
            $worker->write(sprintf('%s:%d', $swooleServer->host, $swooleServer->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame(
            sprintf('%s:%d', ServerFactoryFactory::DEFAULT_HOST, ServerFactoryFactory::DEFAULT_PORT),
            $data
        );
    }

    public function testFactoryCreatesInstanceUsingConfigurationWhenAvailable()
    {
        $config = [
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'host' => 'localhost',
                    'port' => 9501,
                ],
            ],
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function ($worker) {
            $server = ($this->factory)($this->container->reveal());
            $swooleServer = $server->createSwooleServer();
            $worker->write(sprintf('%s:%d', $swooleServer->host, $swooleServer->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('localhost:9501', $data);
    }

    public function testFactoryPassesOptionsFromConfigurationToGeneratedServerFactory()
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
                ],
            ],
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function ($worker) {
            $server = ($this->factory)($this->container->reveal());
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
