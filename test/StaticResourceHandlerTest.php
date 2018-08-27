<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Zend\Expressive\Swoole\Exception;
use Zend\Expressive\Swoole\StaticResourceHandler;

class StaticResourceHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->docRoot = __DIR__ . '/TestAsset';
    }

    public function testConstructorRaisesExceptionForInvalidMiddlewareValue()
    {
        $this->expectException(Exception\InvalidStaticResourceMiddlewareException::class);
        new StaticResourceHandler($this->docRoot, [$this]);
    }

    public function testTypeMapProvidedToConstructorReplacesDefault()
    {
        $map = ['.PNG' => 'image/png'];
        $handler = new StaticResourceHandler($this->docRoot, [], $map);
        $this->assertAttributeSame($map, 'typeMap', $handler);
    }

    public function testIsStaticResourceReturnsFalseWhenFileNotFound()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri' => '/unknown-file.png',
        ];

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertFalse($handler->isStaticResource($request));
    }

    public function testIsStaticResourceReturnsTrueWhenFileIsFound()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri' => '/image.png',
        ];

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertTrue($handler->isStaticResource($request));
    }

    public function testSendStaticResourceDoesNothingIfFileIsNotValid()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri' => '/unknown-file.png',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header(Argument::any())->shouldNotBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile()->shouldNotBeCalled();

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceShouldEmitContentTypeAndSendFileOnSuccessfulMatch()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri' => '/image.png',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'image/png', true)->shouldBeCalled();
        $response->status(200)->shouldBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile($this->docRoot . '/image.png')->shouldBeCalled();

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceShouldUseMiddlewareResultToPopulateSwooleResponse()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri' => '/image.png',
        ];

        $middlewareResponse = new StaticResourceHandler\ResponseValues(
            304,
            ['X-Response' => 'middleware'],
            false
        );

        $middleware = $this->prophesize(StaticResourceHandler\MiddlewareInterface::class);
        $middleware
            ->__invoke($request, $this->docRoot . '/image.png', Argument::any())
            ->willReturn($middlewareResponse);


        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'image/png', true)->shouldBeCalled();
        $response->header('X-Response', 'middleware', true)->shouldBeCalled();
        $response->status(304)->shouldBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile(Argument::any())->shouldNotBeCalled();

        $handler = new StaticResourceHandler($this->docRoot, [$middleware->reveal()]);

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }
}
