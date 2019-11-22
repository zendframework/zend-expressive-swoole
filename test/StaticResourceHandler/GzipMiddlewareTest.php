<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Swoole\StaticResourceHandler;

use Closure;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use ReflectionProperty;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Zend\Expressive\Swoole\Exception\InvalidArgumentException;
use Zend\Expressive\Swoole\StaticResourceHandler\GzipMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
use ZendTest\Expressive\Swoole\AssertResponseTrait;

class GzipMiddlewareTest extends TestCase
{
    use AssertResponseTrait;

    protected function setUp() : void
    {
        $this->staticResponse = $this->prophesize(StaticResourceResponse::class);
        $this->swooleRequest = $this->prophesize(SwooleHttpRequest::class)->reveal();

        $this->next = function ($request, $filename) {
            return $this->staticResponse->reveal();
        };
    }

    public function testConstructorRaisesExceptionOnInvalidCompressionValues()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only allows compression levels up to 9');
        new GzipMiddleware(10);
    }

    public function testMiddlewareDoesNothingIfCompressionLevelLessThan1()
    {
        $this->swooleRequest->header = [
            'accept-encoding' => 'gzip',
        ];
        $middleware = new GzipMiddleware(0);

        $response = $middleware($this->swooleRequest, '/image.png', $this->next);

        $this->staticResponse->setResponseContentCallback(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testMiddlewareDoesNothingIfNoAcceptEncodingRequestHeaderPresent()
    {
        $this->swooleRequest->header = [];
        $middleware = new GzipMiddleware(9);

        $response = $middleware($this->swooleRequest, '/image.png', $this->next);

        $this->staticResponse->setResponseContentCallback(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testMiddlewareDoesNothingAcceptEncodingRequestHeaderContainsUnrecognizedEncoding()
    {
        $this->swooleRequest->header = [
            'accept-encoding' => 'bz2',
        ];
        $middleware = new GzipMiddleware(9);

        $response = $middleware($this->swooleRequest, '/image.png', $this->next);

        $this->staticResponse->setResponseContentCallback(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function acceptedEncodings() : iterable
    {
        foreach (array_values(GzipMiddleware::COMPRESSION_CONTENT_ENCODING_MAP) as $encoding) {
            yield $encoding => [$encoding];
        }
    }

    /**
     * @dataProvider acceptedEncodings
     */
    public function testMiddlewareInjectsResponseContentCallbackWhenItDetectsAnAcceptEncodingItCanHandle(
        string $encoding
    ) {
        $this->swooleRequest->header = [
            'accept-encoding' => $encoding,
        ];
        $middleware = new GzipMiddleware(9);

        $response = $middleware($this->swooleRequest, '/image.png', $this->next);

        $this->staticResponse
            ->setResponseContentCallback(Argument::type(Closure::class))
            ->shouldHaveBeenCalled();
    }

    /**
     * @dataProvider acceptedEncodings
     */
    public function testResponseContentCallbackEmitsExpectedHeadersAndCompressesContent(string $encoding)
    {
        $compressionMap = array_flip(GzipMiddleware::COMPRESSION_CONTENT_ENCODING_MAP);
        $filename = __DIR__ . '/../TestAsset/content.txt';
        $expected = file_get_contents($filename);
        $expected = gzcompress($expected, 9, $compressionMap[$encoding]);

        $this->swooleRequest->header = [
            'accept-encoding' => $encoding,
        ];
        $middleware = new GzipMiddleware(9);

        $staticResponse = new StaticResourceResponse();
        $next = function ($request, $filename) use ($staticResponse) {
            return $staticResponse;
        };

        $response = $middleware($this->swooleRequest, '/content.txt', $next);

        $this->assertSame($staticResponse, $response);

        $r = new ReflectionProperty($response, 'responseContentCallback');
        $r->setAccessible(true);
        $callback = $r->getValue($response);

        $swooleResponse = $this->prophesize(SwooleHttpResponse::class);
        $swooleResponse->header('Content-Encoding', $encoding, true)->shouldBeCalled();
        $swooleResponse->header('Connection', 'close', true)->shouldBeCalled();
        $swooleResponse->header('Content-Length', mb_strlen($expected), true)->shouldBeCalled();

        $swooleResponse->write($expected)->shouldBeCalled();
        $swooleResponse->end()->shouldBeCalled();

        $this->assertNull($callback($swooleResponse->reveal(), $filename));
    }
}
