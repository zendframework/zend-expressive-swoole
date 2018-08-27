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
use Zend\Expressive\Swoole\StaticResourceHandler\OptionsMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\ResponseValues;

class OptionsMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->request = $this->prophesize(Request::class)->reveal();
    }

    public function nonOptionsRequests() : array
    {
        return [
            'GET'     => ['GET'],
            'HEAD'    => ['HEAD'],
        ];
    }

    /**
     * @dataProvider nonOptionsRequests
     */
    public function testMiddlewareDoesNothingForNonOptionsRequests(string $method)
    {
        $this->request->server = ['request_method' => $method];
        $next = function ($request, $filename) {
            return new ResponseValues();
        };

        $middleware = new OptionsMiddleware();

        $response = $middleware($this->request, '/some/filename', $next);

        $this->assertSame(200, $response->getStatus());
        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey('Allow', $headers);
        $this->assertTrue($response->shouldSendContent());
    }

    public function testMiddlewareSetsAllowHeaderAndDisablesContentForOptionsRequests()
    {
        $this->request->server = ['request_method' => 'OPTIONS'];
        $next = function ($request, $filename) {
            return new ResponseValues();
        };

        $middleware = new OptionsMiddleware();

        $response = $middleware($this->request, '/some/filename', $next);

        $this->assertSame(200, $response->getStatus());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Allow', $headers);
        $this->assertSame('GET, HEAD, OPTIONS', $headers['Allow']);
        $this->assertFalse($response->shouldSendContent());
    }
}
