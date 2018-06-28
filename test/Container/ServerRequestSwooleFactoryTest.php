<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use swoole_http_request;
use Zend\Expressive\Swoole\Container\ServerRequestSwooleFactory;
use Zend\Expressive\Swoole\Stream\SwooleStream;

class ServerRequestSwooleFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testConstructor()
    {
        $factory = new ServerRequestSwooleFactory();
        $this->assertInstanceOf(ServerRequestSwooleFactory::class, $factory);
    }

    public function testInvoke()
    {
        $swooleRequest = $this->createMock(swoole_http_request::class);

        $swooleRequest->server = [
            'path_info' => '/',
            'remote_port' => 45314,
            'REQUEST_METHOD' => 'POST',
            'REQUEST_TIME' => time(),
            'REQUEST_URI' => '/some/path',
            'server_port' => 9501,
            'server_protocol' => 'HTTP/2',
        ];

        $swooleRequest->get = [
            'foo' => 'bar',
        ];

        $swooleRequest->post = [
            'bar' => 'baz',
        ];

        $swooleRequest->cookie = [];

        $swooleRequest->files = [
            [
                'tmp_name' => __FILE__,
                'size' => filesize(__FILE__),
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $swooleRequest->header = [
            'Accept' => 'application/*+json',
            'Content-Type' => 'application/json',
            'Cookie' => 'yummy_cookie=choco; tasty_cookie=strawberry',
            'host' => 'localhost:9501',
        ];

        $swooleRequest->method('rawContent')->willReturn('this is the content');

        $factory = new ServerRequestSwooleFactory();

        $result = $factory($this->container->reveal());

        $this->assertTrue(is_callable($result));

        $request = $result($swooleRequest);

        $this->assertInstanceOf(ServerRequestInterface::class, $request);

        $this->assertEquals('2', $request->getProtocolVersion());
        $this->assertEquals('POST', $request->getMethod());

        $this->assertTrue($request->hasHeader('Accept'));
        $this->assertEquals('application/*+json', $request->getHeaderLine('Accept'));
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertTrue($request->hasHeader('Host'));
        $this->assertEquals('localhost:9501', $request->getHeaderLine('Host'));
        $this->assertTrue($request->hasHeader('Cookie'));
        $this->assertEquals('yummy_cookie=choco; tasty_cookie=strawberry', $request->getHeaderLine('Cookie'));

        $this->assertEquals(['foo' => 'bar'], $request->getQueryParams());
        $this->assertEquals(['bar' => 'baz'], $request->getParsedBody());
        $this->assertEquals(
            ['yummy_cookie' => 'choco', 'tasty_cookie' => 'strawberry'],
            $request->getCookieParams()
        );

        $uri = $request->getUri();
        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('localhost', $uri->getHost());
        $this->assertEquals(9501, $uri->getPort());
        $this->assertEquals('/some/path', $uri->getPath());

        $uploadedFiles = $request->getUploadedFiles();
        $this->assertCount(1, $uploadedFiles);
        $uploadedFile = array_shift($uploadedFiles);
        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
        $this->assertEquals(filesize(__FILE__), $uploadedFile->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
        $contents = (string) $uploadedFile->getStream();
        $this->assertEquals(file_get_contents(__FILE__), $contents);

        $body = $request->getBody();
        $this->assertInstanceOf(SwooleStream::class, $body);
        $this->assertEquals('this is the content', (string) $body);
    }
}
