<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Swoole\Http\Response as SwooleHttpResponse;
use Zend\Diactoros\Response;
use Zend\Expressive\Swoole\SwooleEmitter;

class SwooleEmitterTest extends TestCase
{
    protected function setUp() : void
    {
        $this->swooleResponse = $this->prophesize(SwooleHttpResponse::class);
        $this->emitter = new SwooleEmitter($this->swooleResponse->reveal());
    }

    public function testEmit()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->assertTrue($this->emitter->emit($response));

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end('Content!')
            ->shouldHaveBeenCalled();
    }

    public function testMultipleHeaders()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Length', '256');

        $this->assertTrue($this->emitter->emit($response));

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Length', '256')
            ->shouldHaveBeenCalled();
    }

    public function testMultipleSetCookieHeaders()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz')
            ->withAddedHeader(
                'Set-Cookie',
                'baz=qux; Domain=somecompany.co.uk; Path=/; Expires=Wed, 09 Jun 2021 10:18:14 GMT; Secure; HttpOnly'
            );

        $this->assertTrue($this->emitter->emit($response));

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Set-Cookie', Argument::any())
            ->shouldNotBeCalled();
        $this->swooleResponse
            ->cookie('foo', 'bar', 0, '/', '', false, false)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->cookie('bar', 'baz', 0, '/', '', false, false)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->cookie('baz', 'qux', 1623233894, '/', 'somecompany.co.uk', true, true)
            ->shouldHaveBeenCalled();
    }

    public function testEmitWithBigContentBody()
    {
        $content = base64_encode(random_bytes(SwooleEmitter::CHUNK_SIZE)); // CHUNK_SIZE * 1.33333
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write($content);

        $this->assertTrue($this->emitter->emit($response));

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(substr($content, 0, SwooleEmitter::CHUNK_SIZE))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(substr($content, SwooleEmitter::CHUNK_SIZE))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end()
            ->shouldHaveBeenCalled();
    }
}
