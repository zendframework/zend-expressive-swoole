<?php

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Swoole\Server;
use Swoole\Http\Server as SwooleHttpServer;

class ServerTest extends TestCase
{

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class)->reveal();
    }

    public function testCreateSwooleServer()
    {
        $server = $this->prophesize(Server::class);
        $server->createSwooleServer()->willReturn($this->createMock(SwooleHttpServer::class));
        $server = $server->reveal();
        $swooleServer = $server->createSwooleServer();
        $this->assertInstanceOf(SwooleHttpServer::class, $swooleServer);
    }

    public function testCreateSwooleServerWithAppendOptions()
    {
        $options = [
            'daemonize' => false,
            'worker_num' => 1
        ];
        $server = $this->prophesize(Server::class);
        $server->createSwooleServer($options)->willReturn($this->createMock(SwooleHttpServer::class));
        $server = $server->reveal();
        $swooleServer = $server->createSwooleServer($options);
        $this->assertInstanceOf(SwooleHttpServer::class, $swooleServer);
    }
}
