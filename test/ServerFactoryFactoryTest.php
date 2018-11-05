<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Zend\Expressive\Swoole\HttpServerFactory;
use Zend\Expressive\Swoole\ServerFactory;
use Zend\Expressive\Swoole\ServerFactoryFactory;
use const SWOOLE_BASE;
use const SWOOLE_SOCK_TCP;

class ServerFactoryFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var ServerFactoryFactory */
    private $factory;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new ServerFactoryFactory();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->container = null;
        $this->factory = null;
    }

    private function injectSwooleServerIntoContainer() : void
    {
        $swooleServer = new SwooleHttpServer(
            HttpServerFactory::DEFAULT_HOST,
            HttpServerFactory::DEFAULT_PORT,
            SWOOLE_BASE,
            SWOOLE_SOCK_TCP
        );
        $this->container->get(SwooleHttpServer::class)->willReturn($swooleServer);
    }

    public function testFactoryReturnsServerInstanceComposedInContainer() : void
    {
        $process = new Process(function (Process $worker) {
            $this->injectSwooleServerIntoContainer();
            $this->container->get('config')->willReturn([]);
            /** @var ServerFactory $serverFactory */
            $serverFactory = ($this->factory)($this->container->reveal());
            $swooleServer = $serverFactory->createSwooleServer();
            $worker->write(sprintf('%s:%d', $swooleServer->host, $swooleServer->port));
            $worker->exit(0);
        }, true, 1);
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame(
            sprintf('%s:%d', HttpServerFactory::DEFAULT_HOST, HttpServerFactory::DEFAULT_PORT),
            $data
        );
    }

    public function testFactoryPassesOptionsFromConfigurationToGeneratedServerFactory()
    {
        $options = [
            'daemonize' => false,
            'worker_num' => 1,
            'dispatch_mode' => 3,
        ];
        $config = [
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'options' => $options,
                ],
            ],
        ];
        $this->container
            ->get('config')
            ->willReturn($config);

        $process = new Process(function (Process $worker) {
            $this->injectSwooleServerIntoContainer();
            /** @var ServerFactory $serverFactory */
            $serverFactory = ($this->factory)($this->container->reveal());
            $swooleServer = $serverFactory->createSwooleServer();
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
            'host' => HttpServerFactory::DEFAULT_HOST,
            'port' => HttpServerFactory::DEFAULT_PORT,
            'options' => $options,
        ], $data);
    }
}
