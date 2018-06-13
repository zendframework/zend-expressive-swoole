<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use swoole_http_server;
use Zend\Expressive\Swoole\Container\SwooleHttpServerFactory;

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
        $swooleHttpServer = ($this->swooleFactory)($this->container->reveal());

        $this->assertInstanceOf(swoole_http_server::class, $swooleHttpServer);
        $this->assertEquals(SwooleHttpServerFactory::DEFAULT_HOST, $swooleHttpServer->host);
        $this->assertEquals(SwooleHttpServerFactory::DEFAULT_PORT, $swooleHttpServer->port);
    }

    public function testInvokeWithConfig()
    {
        $config = [
            'swoole' => [
                'host' => 'localhost',
                'port' => 9501
            ]
        ];
        $this->container->get('config')
            ->willReturn($config);
        $swooleHttpServer = ($this->swooleFactory)($this->container->reveal());

        $this->assertInstanceOf(swoole_http_server::class, $swooleHttpServer);
        $this->assertEquals('localhost', $swooleHttpServer->host);
        $this->assertEquals(9501, $swooleHttpServer->port);
    }
}
