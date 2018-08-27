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
use Zend\Expressive\Swoole\Exception\InvalidArgumentException;
use Zend\Expressive\Swoole\StaticResourceHandler\CacheControlMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\ResponseValues;

class CacheControlMiddlewareTest extends TestCase
{
    public function testConstructorRaisesExceptionForInvalidRegexKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache-Control regex');
        new CacheControlMiddleware([
            'not-a regex' => [],
        ]);
    }

    public function testConstructorRaisesExceptionForNonArrayDirectives()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an array of strings');
        new CacheControlMiddleware([
            '/\.js$/' => 'this-is-invalid',
        ]);
    }

    public function testConstructorRaisesExceptionForNonStringDirective()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('each must be a string');
        new CacheControlMiddleware([
            '/\.js$/' => [42],
        ]);
    }

    public function testConstructorRaisesExceptionForInvalidDirective()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache-Control directive');
        new CacheControlMiddleware([
            '/\.js$/' => ['this-is-not-valid'],
        ]);
    }

    public function testMiddlewareDoesNothingIfPathDoesNotMatchAnyDirectives()
    {
        $middleware = new CacheControlMiddleware([
            '/\.txt$/' => [
                'public',
                'no-transform',
            ]
        ]);

        $request = $this->prophesize(Request::class)->reveal();
        $request->server = [
            'request_uri' => '/some/path.html',
        ];

        $next = function ($request, $filename) {
            return new ResponseValues();
        };

        $response = $middleware($request, 'some/path.html', $next);

        $this->assertEquals(200, $response->getStatus());
        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey('Cache-Control', $headers);
        $this->assertTrue($response->shouldSendContent());
    }

    public function testMiddlewareAddsCacheControlHeaderIfPathMatchesADirective()
    {
        $middleware = new CacheControlMiddleware([
            '/\.txt$/' => [
                'public',
                'no-transform',
            ]
        ]);

        $request = $this->prophesize(Request::class)->reveal();
        $request->server = [
            'request_uri' => '/some/path.txt',
        ];

        $next = function ($request, $filename) {
            return new ResponseValues();
        };

        $response = $middleware($request, 'some/path.html', $next);

        $this->assertEquals(200, $response->getStatus());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertEquals('public, no-transform', $headers['Cache-Control']);
        $this->assertTrue($response->shouldSendContent());
    }
}
