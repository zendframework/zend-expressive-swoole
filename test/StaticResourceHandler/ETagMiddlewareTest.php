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
use Zend\Expressive\Swoole\StaticResourceHandler\ETagMiddleware;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
use ZendTest\Expressive\Swoole\AssertResponseTrait;

class ETagMiddlewareTest extends TestCase
{
    use AssertResponseTrait;

    public function setUp()
    {
        $this->next = function ($request, $filename) {
            return new StaticResourceResponse();
        };
        $this->request = $this->prophesize(Request::class)->reveal();
    }

    public function testConstructorRaisesExceptionForInvalidRegexInDirectiveList()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('regex');
        new ETagMiddleware(['not-a-valid-regex']);
    }

    public function testConstructorRaisesExceptionForInvalidETagValidationType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ETag validation type');
        new ETagMiddleware([], 'invalid-etag-validation-type');
    }

    public function testMiddlewareDoesNothingWhenRequestPathDoesNotMatchADirective()
    {
        $this->request->server = [
            'request_uri' => '/any/path/at/all',
        ];

        $middleware = new ETagMiddleware([]);

        $response = $middleware($this->request, '/any/path/at/all', $this->next);

        $this->assertStatus(200, $response);
        $this->assertHeaderNotExists('ETag', $response);
        $this->assertShouldSendContent($response);
    }

    public function expectedEtagProvider() : array
    {
        $filename = __DIR__ . '/../TestAsset/image.png';
        $lastModified = filemtime($filename);
        $filesize = filesize($filename);

        $weakETag   = sprintf('W/"%x-%x"', $lastModified, $filesize);
        $strongETag = md5_file($filename);

        return [
            'weak'   => [ETagMiddleware::ETAG_VALIDATION_WEAK, '/\.png$/', $filename, $weakETag],
            'strong' => [ETagMiddleware::ETAG_VALIDATION_STRONG, '/\.png$/', $filename, $strongETag],
        ];
    }

    /**
     * @dataProvider expectedEtagProvider
     */
    public function testMiddlewareProvidesETagAccordingToValidationStrengthWhenFileMatchesDirective(
        string $validationType,
        string $regex,
        string $filename,
        string $expectedETag
    ) {
        $this->request->server = [
            'request_uri' => '/images/' . basename($filename),
        ];

        $middleware = new ETagMiddleware([$regex], $validationType);

        $response = $middleware($this->request, $filename, $this->next);

        $this->assertStatus(200, $response);
        $this->assertHeaderExists('ETag', $response);
        $this->assertHeaderSame($expectedETag, 'ETag', $response);
        $this->assertShouldSendContent($response);
    }

    public function clientMatchHeaders() : iterable
    {
        $clientHeaders = ['if-match', 'if-none-match'];

        foreach ($clientHeaders as $header) {
            foreach ($this->expectedEtagProvider() as $case => $arguments) {
                $name = sprintf('%s - %s', $header, $case);
                array_unshift($arguments, $header);
                yield $name => $arguments;
            }
        }
    }

    /**
     * @dataProvider clientMatchHeaders
     */
    public function testMiddlewareDisablesResponseContentWhenETagMatchesClientHeader(
        string $clientHeader,
        string $validationType,
        string $regex,
        string $filename,
        string $expectedETag
    ) {
        $this->request->server = [
            'request_uri' => '/images/' . basename($filename),
        ];
        $this->request->header = [
            $clientHeader => $expectedETag,
        ];

        $middleware = new ETagMiddleware([$regex], $validationType);

        $response = $middleware($this->request, $filename, $this->next);

        $this->assertStatus(304, $response);
        $this->assertHeaderExists('ETag', $response);
        $this->assertHeaderSame($expectedETag, 'ETag', $response);
        $this->assertShouldNotSendContent($response);
    }
}
