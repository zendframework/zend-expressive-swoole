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
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;
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
            'worker_num' => 4,
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
            'worker_num' => 4,
        ]);
        $swooleServer->method('on')
            ->willReturn(null);

        $swooleServer->method('start')
            ->willReturn(null);

        $this->staticResourceHandler
            ->processStaticResource(Argument::any())
            ->willReturn(null);

        $requestHandler = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager,
            $this->serverFactory,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );
        $requestHandler->exitFromCommand = false;

        $swooleServer->expects($this->once())
            ->method('start');

        // Listeners are attached to each of:
        // - start
        // - workerstart
        // - request
        $swooleServer->expects($this->exactly(3))
            ->method('on');

        // Clear command options, like phpunit --colors=always
        $_SERVER['argv'] = [
            $_SERVER['argv'][0],
            'start'
        ];
        $_SERVER['argc'] = 2;
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
        $request->get = [];

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

        $this->expectOutputRegex('/127\.0\.0\.1\s.*?\s"GET[^"]+" 200.*?\R$/');
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
        $request->get = [];

        $response = $this->prophesize(SwooleHttpResponse::class);
        $response
            ->status(200)
            ->shouldBeCalled();
        $response
            ->end($content)
            ->shouldBeCalled();

        $this->staticResourceHandler
            ->processStaticResource($request, $response->reveal())
            ->willReturn(null);

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

        $this->expectOutputRegex('/127\.0\.0\.1\s.*?\s"GET[^"]+" 200.*?\R$/');
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
        $request->get = [];

        $response = $this->prophesize(SwooleHttpResponse::class)->reveal();

        $staticResponse = $this->prophesize(StaticResourceResponse::class);
        $staticResponse->getStatus()->willReturn(200);
        $staticResponse->getContentLength()->willReturn(200);

        $this->staticResourceHandler
            ->processStaticResource($request, $response)
            ->will([$staticResponse, 'reveal']);

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

        $this->expectOutputRegex('/127\.0\.0\.1\s.*?\s"GET[^"]+" 200.*?\R$/');
    }
}
