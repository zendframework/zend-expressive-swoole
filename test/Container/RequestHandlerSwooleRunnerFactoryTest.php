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
use Psr\Http\Server\RequestHandlerInterface;
use swoole_http_server;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Swoole\Container\RequestHandlerSwooleRunner;
use Zend\Expressive\Swoole\Container\RequestHandlerSwooleRunnerFactory;

class RequestHandlerSwooleRunnerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->requestHandler = $this->prophesize(RequestHandlerInterface::class);
        $this->serverRequest = $this->prophesize(ServerRequestInterface::class);
        $this->serverRequestError = $this->prophesize(ServerRequestErrorResponseGenerator::class);
        // used createMock instead of prophesize for issue
        $this->swooleHttpServer = $this->createMock(swoole_http_server::class);


        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container
            ->get(RequestHandlerInterface::class)
            ->willReturn($this->requestHandler->reveal());
        $this->container
            ->get(ServerRequestInterface::class)
            ->willReturn(function () {
                return $this->serverRequest->reveal();
            });
        $this->container
            ->get(ServerRequestErrorResponseGenerator::class)
            ->willReturn(function () {
                return $this->serverRequestError->reveal();
            });
        $this->container
            ->get(swoole_http_server::class)
            ->willReturn($this->swooleHttpServer);
    }

    public function testConstructor()
    {
        $request = new RequestHandlerSwooleRunnerFactory();
        $this->assertInstanceOf(RequestHandlerSwooleRunnerFactory::class, $request);
    }

    public function testInvoke()
    {
        $request = new RequestHandlerSwooleRunnerFactory();
        $result = $request($this->container->reveal());
        $this->assertInstanceOf(RequestHandlerSwooleRunner::class, $result);
    }
}
