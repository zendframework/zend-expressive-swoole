<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\StaticResourceHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\StaticResourceHandler\MiddlewareInterface;
use Zend\Expressive\Swoole\StaticResourceHandler\MiddlewareQueue;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
use ZendTest\Expressive\Swoole\AssertResponseTrait;

class MiddlewareQueueTest extends TestCase
{
    use AssertResponseTrait;

    public function setUp()
    {
        $this->request = $this->prophesize(Request::class)->reveal();
    }

    public function testEmptyMiddlewareQueueReturnsSuccessfulResponseValue()
    {
        $queue = new MiddlewareQueue([]);

        $response = $queue($this->request, 'some/filename.txt');

        $this->assertInstanceOf(StaticResourceResponse::class, $response);
        $this->assertStatus(200, $response);
        $this->assertHeadersEmpty($response);
        $this->assertShouldSendContent($response);
    }

    public function testReturnsResponseGeneratedByMiddleware()
    {
        $response = $this->prophesize(StaticResourceResponse::class)->reveal();

        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->__invoke($this->request, 'some/filename.txt', Argument::type(MiddlewareQueue::class))
            ->willReturn($response);

        $queue = new MiddlewareQueue([$middleware->reveal()]);

        $result = $queue($this->request, 'some/filename.txt');

        $this->assertSame($response, $result);
    }

    public function testEachMiddlewareReceivesSameQueueInstance()
    {
        $second = $this->prophesize(MiddlewareInterface::class);

        $first = $this->prophesize(MiddlewareInterface::class);
        $first
            ->__invoke($this->request, 'some/filename.txt', Argument::that(function ($queue) {
                TestCase::assertInstanceOf(MiddlewareQueue::class, $queue);
                return true;
            }))
            ->will(function ($args) use ($second) {
                $second
                    ->__invoke($args[0], $args[1], $args[2])
                    ->will(function ($args) {
                        $next = $args[2];
                        $response = $next($args[0], $args[1]);
                        $response->setStatus(304);
                        $response->addHeader('X-Hit', 'second');
                        $response->disableContent();
                        return $response;
                    });

                $next = $args[2];
                return $next($args[0], $args[1]);
            });

        $queue = new MiddlewareQueue([
            $first->reveal(),
            $second->reveal(),
        ]);

        $response = $queue($this->request, 'some/filename.txt');

        $this->assertInstanceOf(StaticResourceResponse::class, $response);
        $this->assertStatus(304, $response);
        $this->assertHeaderExists('X-Hit', $response);
        $this->assertHeaderSame('second', 'X-Hit', $response);
        $this->assertShouldNotSendContent($response);
    }
}
