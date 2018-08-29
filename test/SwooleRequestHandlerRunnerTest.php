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
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;
use Zend\Expressive\Swoole\ServerFactory;
use Zend\Expressive\Swoole\StaticResourceHandlerInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

class SwooleRequestHandlerRunnerTest extends TestCase
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

        $this->pidManager = $this->prophesize(PidManager::class)->reveal();

        $serverFactory = $this->prophesize(ServerFactory::class);
        $serverFactory->createSwooleServer([
            'daemonize' => false,
            'worker_num' => 1
        ])->willReturn($this->createMock(SwooleHttpServer::class));
        $this->serverFactory = $serverFactory->reveal();

        $this->staticResourceHandler = $this->prophesize(StaticResourceHandlerInterface::class);

        $this->logger = null;

        $this->config = [
            'options' => [
                'document_root' => __DIR__ . '/TestAsset'
            ]
        ];
    }

    public function testConstructor()
    {
        $requestHandler = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $requestHandler);
        $this->assertInstanceOf(RequestHandlerRunner::class, $requestHandler);
    }

    public function testRun()
    {
        $swooleServer = $this->serverFactory->createSwooleServer([
            'daemonize' => false,
            'worker_num' => 1
        ]);
        $swooleServer->method('on')
            ->willReturn(null);

        $swooleServer->method('start')
            ->willReturn(null);

        $this->staticResourceHandler
            ->isStaticResource(Argument::any())
            ->willReturn(false);

        $this->staticResourceHandler
            ->sendStaticResource(Argument::any())
            ->shouldNotBeCalled();

        $requestHandler = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $swooleServer->expects($this->once())
            ->method('start');

        // Listeners are attached to each of:
        // - start
        // - workerstart
        // - request
        $swooleServer->expects($this->exactly(3))
            ->method('on');

        // Clear command options, like phpunit --colors=always
        $_SERVER['argv'] = [$_SERVER['argv'][0]];
        $_SERVER['argc'] = 1;
        $requestHandler->run();
    }

    public function testOnStart()
    {
        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $runner->onStart($swooleServer = $this->createMock(SwooleHttpServer::class));
        $this->expectOutputString(sprintf(
            "Swoole is running at :0, in %s\n",
            getcwd()
        ));
    }

    public function testOnRequestDelegatesToApplicationWhenNoStaticResourceHandlerPresent()
    {
        $content = 'Content!';
        $psr7Response = (new Response());
        $psr7Response->getBody()->write($content);

        $this->requestHandler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willReturn($psr7Response);

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response
            ->status(200)
            ->shouldBeCalled();
        $response
            ->end($content)
            ->shouldBeCalled();

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            null,
            $this->logger
        );

        $runner->onRequest($request, $response->reveal());

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/\R$/");
    }

    public function testOnRequestDelegatesToApplicationWhenStaticResourceHandlerDoesNotMatchPath()
    {
        $content = 'Content!';
        $psr7Response = (new Response());
        $psr7Response->getBody()->write($content);

        $this->requestHandler
            ->handle(Argument::type(ServerRequestInterface::class))
            ->willReturn($psr7Response);

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response
            ->status(200)
            ->shouldBeCalled();
        $response
            ->end($content)
            ->shouldBeCalled();

        $this->staticResourceHandler
            ->isStaticResource($request)
            ->willReturn(false);
        $this->staticResourceHandler
            ->sendStaticResource(Argument::any())
            ->shouldNotBeCalled();

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $runner->onRequest($request, $response->reveal());

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/\R$/");
    }

    public function testOnRequestDelegatesToStaticResourceHandlerOnMatch()
    {
        $this->requestHandler
            ->handle(Argument::any())
            ->shouldNotBeCalled();

        $request = $this->prophesize(SwooleHttpRequest::class)->reveal();
        $request->server = [
            'request_uri'    => '/',
            'remote_addr'    => '127.0.0.1',
            'request_method' => 'GET'
        ];

        $response = $this->prophesize(SwooleHttpResponse::class)->reveal();

        $this->staticResourceHandler
            ->isStaticResource($request)
            ->willReturn(true);
        $this->staticResourceHandler
            ->sendStaticResource($request, $response)
            ->shouldBeCalled();

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $runner->onRequest($request, $response);

        $this->expectOutputRegex("/127\.0\.0\.1 - GET \/\R$/");
    }
}
