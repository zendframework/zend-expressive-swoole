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
use Zend\Expressive\Swoole\StaticResourceHandler\HeadMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\ResponseValues;

class HeadMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->next = function ($request, $filename) {
            return new ResponseValues();
        };
        $this->request = $this->prophesize(Request::class)->reveal();
    }

    public function nonHeadMethods() : array
    {
        return [
            'GET'     => ['GET'],
            'POST'    => ['POST'],
            'PATCH'   => ['PATCH'],
            'PUT'     => ['PUT'],
            'DELETE'  => ['DELETE'],
            'CONNECT' => ['CONNECT'],
            'OPTIONS' => ['OPTIONS'],
        ];
    }

    /**
     * @dataProvider nonHeadMethods
     */
    public function testMiddlewareDoesNothingIfRequestMethodIsNotHead(string $method)
    {
        $this->request->server = [
            'request_method' => $method,
        ];
        $middleware = new HeadMiddleware();

        $response = $middleware($this->request, '/some/path', $this->next);

        $this->assertTrue($response->shouldSendContent());
    }

    public function testMiddlewareDisablesContentWhenHeadMethodDetected()
    {
        $this->request->server = [
            'request_method' => 'HEAD',
        ];
        $middleware = new HeadMiddleware();

        $response = $middleware($this->request, '/some/path', $this->next);

        $this->assertFalse($response->shouldSendContent());
    }
}
