<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\StaticResourceHandler;

use PHPUnit\Framework\TestCase;
use Swoole\Http\Response as SwooleResponse;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

class StaticResourceResponseTest extends TestCase
{
    public function testSendSwooleResponsePopulatesStatusAndHeadersAndCallsContentCallback()
    {
        $expectedFilename = '/image.png';
        $swooleResponse = $this->prophesize(SwooleResponse::class);
        $response = new StaticResourceResponse();
        $response->setStatus(302);
        $response->addHeader('Location', 'https://example.com');
        $response->addHeader('Expires', '3600');
        $response->setResponseContentCallback(function ($response, $filename) use ($expectedFilename) {
            TestCase::assertInstanceOf(SwooleResponse::class, $response);
            TestCase::assertSame($expectedFilename, $filename);
        });

        $this->assertNull($response->sendSwooleResponse($swooleResponse->reveal(), $expectedFilename));

        $swooleResponse->status(302)->shouldHaveBeenCalled();
        $swooleResponse->header('Location', 'https://example.com', true)->shouldHaveBeenCalled();
        $swooleResponse->header('Expires', '3600', true)->shouldHaveBeenCalled();
    }

    public function testSendSwooleResponseSkipsSendingContentWhenContentDisabled()
    {
        $filename = '/image.png';
        $swooleResponse = $this->prophesize(SwooleResponse::class);
        $response = new StaticResourceResponse();
        $response->setStatus(302);
        $response->addHeader('Location', 'https://example.com');
        $response->addHeader('Expires', '3600');
        $response->setResponseContentCallback(function ($response, $filename) {
            TestCase::fail('Callback should not have been called');
        });
        $response->disableContent();

        $this->assertNull($response->sendSwooleResponse($swooleResponse->reveal(), $filename));

        $swooleResponse->status(302)->shouldHaveBeenCalled();
        $swooleResponse->header('Location', 'https://example.com', true)->shouldHaveBeenCalled();
        $swooleResponse->header('Expires', '3600', true)->shouldHaveBeenCalled();
    }
}
