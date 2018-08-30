<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\StaticResourceHandler;

use PHPUnit\Framework\TestCase;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\StaticResourceHandler\ContentTypeFilterMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
use ZendTest\Expressive\Swoole\AssertResponseTrait;

class ContentTypeFilterMiddlewareTest extends TestCase
{
    use AssertResponseTrait;

    public function setUp()
    {
        $this->request = $this->prophesize(Request::class)->reveal();
    }

    public function testPassingNoArgumentsToConstructorSetsDefaultTypeMap()
    {
        $middleware = new ContentTypeFilterMiddleware();
        $this->assertAttributeSame(
            ContentTypeFilterMiddleware::TYPE_MAP_DEFAULT,
            'typeMap',
            $middleware
        );
    }

    public function testCanProvideAlternateTypeMapViaConstructor()
    {
        $typeMap = [
            'asc' => 'application/octet-stream',
        ];
        $middleware = new ContentTypeFilterMiddleware($typeMap);
        $this->assertAttributeSame($typeMap, 'typeMap', $middleware);
    }

    public function testMiddlewareReturnsFailureResponseIfFileNotFound()
    {
        $next = function ($request, $filename) {
            TestCase::fail('Should not have invoked next middleware');
        };
        $middleware = new ContentTypeFilterMiddleware();

        $response = $middleware($this->request, __DIR__ . '/not-a-valid-file.png', $next);

        $this->assertInstanceOf(StaticResourceResponse::class, $response);
        $this->assertTrue($response->isFailure());
    }

    public function testMiddlewareReturnsFailureResponseIfFileNotAllowedByTypeMap()
    {
        $next = function ($request, $filename) {
            TestCase::fail('Should not have invoked next middleware');
        };
        $middleware = new ContentTypeFilterMiddleware([
            'txt' => 'text/plain',
        ]);

        $response = $middleware($this->request, __DIR__ . '/../image.png', $next);

        $this->assertInstanceOf(StaticResourceResponse::class, $response);
        $this->assertTrue($response->isFailure());
    }

    public function testMiddlewareAddsContentTypeToResponseWhenResourceLocatedAndAllowed()
    {
        $expected = new StaticResourceResponse();
        $next = function ($request, $filename) use ($expected) {
            return $expected;
        };
        $middleware = new ContentTypeFilterMiddleware();

        $response = $middleware($this->request, __DIR__ . '/../TestAsset/image.png', $next);

        $this->assertSame($expected, $response);
        $this->assertHeaderSame('image/png', 'Content-Type', $response);
    }
}
