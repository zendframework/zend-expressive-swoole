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
use Zend\Expressive\Swoole\StaticResourceHandler;

class StaticResourceHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->docRoot = __DIR__ . '/TestAsset';
    }

    public function supportedHttpMethods() : array
    {
        return [
            'GET'  => ['GET'],
            'HEAD' => ['HEAD'],
        ];
    }

    public function unsupportedHttpMethods() : array
    {
        return [
            'CONNECT' => ['CONNECT'],
            'DELETE'  => ['DELETE'],
            'PATCH'   => ['PATCH'],
            'POST'    => ['POST'],
            'PUT'     => ['PUT'],
            'TRACE'   => ['TRACE'],
        ];
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

    /**
     * @dataProvider unsupportedHttpMethods
     */
    public function testSendStaticResourceReturns405ResponseForUnsupportedMethodMatchingFile(string $method)
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_method' => $method,
            'request_uri'    => '/image.png',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Allow', 'GET, HEAD, OPTIONS', true)->shouldBeCalled();
        $response->status(405)->shouldBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile()->shouldNotBeCalled();

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceEmitsAllowHeaderWith200ResponseForOptionsRequest()
    {
        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_method' => 'OPTIONS',
            'request_uri'    => '/image.png',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'image/png', true)->shouldBeCalled();
        $response->header('Allow', 'GET, HEAD, OPTIONS', true)->shouldBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile()->shouldNotBeCalled();

        $handler = new StaticResourceHandler($this->docRoot);

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceEmitsContentAndHeadersMatchingDirectivesForPath()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Cache-Control', 'public, no-transform', true)->shouldBeCalled();
        $response->header('Last-Modified', $lastModifiedFormatted, true)->shouldBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile($file)->shouldBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            ['/\.txt$/' => ['public', 'no-transform']],
            ['/\.txt$/'],
            ['/\.txt$/'],
            StaticResourceHandler::ETAG_VALIDATION_WEAK
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceEmitsHeadersOnlyWhenMatchingDirectivesForHeadRequestToKnownPath()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [];
        $request->server = [
            'request_method' => 'HEAD',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Cache-Control', 'public, no-transform', true)->shouldBeCalled();
        $response->header('Last-Modified', $lastModifiedFormatted, true)->shouldBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            ['/\.txt$/' => ['public', 'no-transform']],
            ['/\.txt$/'],
            ['/\.txt$/'],
            StaticResourceHandler::ETAG_VALIDATION_WEAK
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceEmitsAllowHeaderWithHeadersAndNoBodyWhenMatchingOptionsRequestToKnownPath()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [];
        $request->server = [
            'request_method' => 'OPTIONS',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', 'GET, HEAD, OPTIONS', true)->shouldBeCalled();
        $response->header('Cache-Control', 'public, no-transform', true)->shouldBeCalled();
        $response->header('Last-Modified', $lastModifiedFormatted, true)->shouldBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            ['/\.txt$/' => ['public', 'no-transform']],
            ['/\.txt$/'],
            ['/\.txt$/'],
            StaticResourceHandler::ETAG_VALIDATION_WEAK
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceViaGetSkipsClientSideCacheMatchingIfNoETagOrLastModifiedHeadersConfigured()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-modified-since' => $lastModifiedFormatted,
            'if-match' => $etag,
        ];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', 'public, no-transform', true)->shouldBeCalled();
        $response->header('Last-Modified', Argument::any())->shouldNotBeCalled();
        $response->header('ETag', Argument::any())->shouldNotBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile($file)->shouldBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            ['/\.txt$/' => ['public', 'no-transform']]
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceViaHeadSkipsClientSideCacheMatchingIfNoETagOrLastModifiedHeadersConfigured()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-modified-since' => $lastModifiedFormatted,
            'if-match' => $etag,
        ];
        $request->server = [
            'request_method' => 'HEAD',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', 'public, no-transform', true)->shouldBeCalled();
        $response->header('Last-Modified', Argument::any())->shouldNotBeCalled();
        $response->header('ETag', Argument::any())->shouldNotBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            ['/\.txt$/' => ['public', 'no-transform']]
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceViaGetHitsClientSideCacheMatchingIfETagMatchesIfMatchValue()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-match' => $etag,
        ];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', Argument::any())->shouldNotBeCalled();
        $response->header('Last-Modified', Argument::any())->shouldNotBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(304)->shouldBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            [],
            [],
            ['/\.txt$/']
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceViaGetHitsClientSideCacheMatchingIfETagMatchesIfNoneMatchValue()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $etag = sprintf('W/"%x-%x"', $lastModified, filesize($file));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-none-match' => $etag,
        ];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', Argument::any())->shouldNotBeCalled();
        $response->header('Last-Modified', Argument::any())->shouldNotBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(304)->shouldBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            [],
            [],
            ['/\.txt$/']
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceCanGenerateStrongETagValue()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $etag = md5_file($file);

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', Argument::any())->shouldNotBeCalled();
        $response->header('Last-Modified', Argument::any())->shouldNotBeCalled();
        $response->header('ETag', $etag, true)->shouldBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile($file)->shouldBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            [],
            [],
            ['/\.txt$/'],
            StaticResourceHandler::ETAG_VALIDATION_STRONG
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testSendStaticResourceViaGetHitsClientSideCacheMatchingIfLastModifiedMatchesIfModifiedSince()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-modified-since' => $lastModifiedFormatted,
        ];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', Argument::any())->shouldNotBeCalled();
        $response->header('Last-Modified', $lastModifiedFormatted, true)->shouldBeCalled();
        $response->header('ETag', Argument::any())->shouldNotBeCalled();
        $response->status(304)->shouldBeCalled();
        $response->end()->shouldBeCalled();
        $response->sendfile($file)->shouldNotBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            [],
            ['/\.txt$/']
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }

    public function testGetDoesNotHitClientSideCacheMatchingIfLastModifiedDoesNotMatchIfModifiedSince()
    {
        $file = $this->docRoot . '/content.txt';
        $contentType = 'text/plain';
        $lastModified = filemtime($file);
        $lastModifiedFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));
        $ifModifiedSince = $lastModified - 3600;
        $ifModifiedSinceFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $ifModifiedSince));

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->header = [
            'if-modified-since' => $ifModifiedSinceFormatted,
        ];
        $request->server = [
            'request_method' => 'GET',
            'request_uri'    => '/content.txt',
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response->header('Content-Type', 'text/plain', true)->shouldBeCalled();
        $response->header('Allow', Argument::any())->shouldNotBeCalled();
        $response->header('Cache-Control', Argument::any())->shouldNotBeCalled();
        $response->header('Last-Modified', $lastModifiedFormatted, true)->shouldBeCalled();
        $response->header('ETag', Argument::any())->shouldNotBeCalled();
        $response->status(Argument::any())->shouldNotBeCalled();
        $response->end()->shouldNotBeCalled();
        $response->sendfile($file)->shouldBeCalled();

        $handler = new StaticResourceHandler(
            $this->docRoot,
            null,
            [],
            ['/\.txt$/']
        );

        $this->assertTrue($handler->isStaticResource($request));
        $this->assertNull($handler->sendStaticResource($request, $response->reveal()));
    }
}
