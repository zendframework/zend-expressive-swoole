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
use Psr\Http\Message\ServerRequestInterface;
use swoole_http_request;
use Zend\Expressive\Swoole\Container\ServerRequestSwooleFactory;

class ServerRequestSwooleFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->request = $this->prophesize(swoole_http_request::class);
        $this->request->header = [];
        $this->request->server = ['REQUEST_METHOD' => 'GET'];
    }

    public function testConstructor()
    {
        $factory = new ServerRequestSwooleFactory();
        $this->assertInstanceOf(ServerRequestSwooleFactory::class, $factory);
    }

    public function testInvoke()
    {
        $factory = new ServerRequestSwooleFactory();

        $result = $factory($this->container->reveal());
        $this->assertTrue(is_callable($result));

        $serverRequest = $result($this->request->reveal());
        $this->assertInstanceOf(ServerRequestInterface::class, $serverRequest);
    }
}
