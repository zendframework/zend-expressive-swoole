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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Zend\Diactoros\Response;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Swoole\PidManager;
use Zend\Expressive\Swoole\RequestHandlerSwooleRunner;
use Zend\Expressive\Swoole\Server;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

class RequestHandlerSwooleRunnerTest extends TestCase
{
    public function setUp()
    {
        $this->requestHandler = $this->prophesize(RequestHandlerInterface::class);
        $this->serverRequestFactory = function () {
            return $this->prophesize(ServerRequestInterface::class)->reveal();
        };
        $this->serverRequestError = function () {
            return $this->prophesize(ServerRequestErrorResponseGenerator::class)->reveal();
        };
        $server = $this->prophesize(Server::class);
        $server->createSwooleServer([
            'daemonize' => false,
            'worker_num' => 1
        ])->willReturn($this->createMock(SwooleHttpServer::class));
        $this->server = $server->reveal();

        $this->logger = null;

        $this->pidManager = $this->prophesize(PidManager::class)->reveal();
        $this->config = [
            'options' => [
                'document_root' => __DIR__ . '/TestAsset'
            ]
        ];
    }

    public function testConstructor()
    {
        $requestHandler = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );
        $this->assertInstanceOf(RequestHandlerSwooleRunner::class, $requestHandler);
        $this->assertInstanceOf(RequestHandlerRunner::class, $requestHandler);
    }

    public function testRun()
    {
        $swooleServer = $this->server->createSwooleServer([
            'daemonize' => false,
            'worker_num' => 1
        ]);
        $swooleServer->method('on')
            ->willReturn(null);

        $swooleServer->method('start')
            ->willReturn(null);

        $requestHandler = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );

        $swooleServer->expects($this->once())
            ->method('start');

        $swooleServer->expects($this->exactly(2))
            ->method('on');

        // Clear command options, like phpunit --colors=always
        $_SERVER['argv'] = [$_SERVER['argv'][0]];
        $_SERVER['argc'] = 1;
        $requestHandler->run();
    }

    public function testOnStart()
    {
        $runner = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );

        $runner->onStart($swooleServer = $this->createMock(SwooleHttpServer::class));
        $this->expectOutputString("Swoole is running at :0\n");
    }

    public function testOnRequest()
    {
        $content = 'Content!';
        $contentType = 'text/plain';
        $psr7Response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', $contentType);
        $psr7Response->getBody()->write($content);

        $this->requestHandler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willReturn($psr7Response);

        $runner = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];
        $response = $this->prophesize(SwooleHttpResponse::class);

        $runner->onRequest($request, $response->reveal());

        $response
            ->status(200)
            ->shouldHaveBeenCalled();
        $response
            ->end($content)
            ->shouldHaveBeenCalled();
        $response
            ->header('Content-Type', $contentType)
            ->shouldHaveBeenCalled();

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/\R$/");
    }

    public function testOnRequestWithStaticImageSuccess()
    {
        $runner = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/image.png',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];
        $response = $this->prophesize(SwooleHttpResponse::class);

        $runner->onRequest($request, $response->reveal());

        $response
            ->header('Content-Type', 'image/png')
            ->shouldHaveBeenCalled();
        $response
            ->sendfile(__DIR__ . '/TestAsset/image.png')
            ->shouldHaveBeenCalled();

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/image\.png\R$/");
    }

    public function testInternalCacheStaticFile()
    {
        $runner = new RequestHandlerSwooleRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->server,
            $this->config,
            $this->logger,
            $this->pidManager
        );

        $reflector = new ReflectionClass($runner);
        $cacheTypeFile = $reflector->getProperty('cacheTypeFile');
        $cacheTypeFile->setAccessible(true);

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/image.png',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];
        $response = $this->prophesize(SwooleHttpResponse::class);

        $runner->onRequest($request, $response->reveal());

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/image\.png\R$/");
        $this->assertEquals(
            [__DIR__ . '/TestAsset/image.png' => 'image/png'],
            $cacheTypeFile->getValue($runner)
        );
    }
}
