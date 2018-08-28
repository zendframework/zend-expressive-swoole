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
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
use ZendTest\Expressive\Swoole\AssertResponseTrait;

class HeadMiddlewareTest extends TestCase
{
    use AssertResponseTrait;

    public function setUp()
    {
        $this->next = function ($request, $filename) {
            return new StaticResourceResponse();
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

        $this->assertShouldSendContent($response);
    }

    public function testMiddlewareDisablesContentWhenHeadMethodDetected()
    {
        $this->request->server = [
            'request_method' => 'HEAD',
        ];
        $middleware = new HeadMiddleware();

        $response = $middleware($this->request, '/some/path', $this->next);

        $this->assertShouldNotSendContent($response);
    }
}
