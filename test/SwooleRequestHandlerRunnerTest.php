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
use Zend\Expressive\Swoole\HotCodeReload\Reloader;
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

        $this->pidManager = $this->prophesize(PidManager::class);

        $this->httpServer = $this->createMock(SwooleHttpServer::class);

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
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );
        $this->assertInstanceOf(SwooleRequestHandlerRunner::class, $requestHandler);
        $this->assertInstanceOf(RequestHandlerRunner::class, $requestHandler);
    }

    public function testRun()
    {
        $this->pidManager
            ->read()
            ->willReturn([]);

        $this->httpServer
            ->method('on')
            ->willReturn(null);

        $this->httpServer
            ->method('start')
            ->willReturn(null);

        $this->staticResourceHandler
            ->processStaticResource(Argument::any())
            ->willReturn(null);

        $requestHandler = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $this->httpServer
            ->expects($this->once())
            ->method('start');

        // Listeners are attached to each of:
        // - start
        // - workerstart
        // - request
        // - shutdown
        $this->httpServer
            ->expects($this->exactly(4))
            ->method('on');

        $requestHandler->run();
    }

    public function testOnStart()
    {
        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
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
            $this->pidManager->reveal(),
            $this->httpServer,
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
            $this->pidManager->reveal(),
            $this->httpServer,
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
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger
        );

        $runner->onRequest($request, $response);

        $this->expectOutputRegex('/127\.0\.0\.1\s.*?\s"GET[^"]+" 200.*?\R$/');
    }

    public function testProcessNameIsUsedToCreateMasterProcessNameOnStart()
    {
        if (\PHP_OS === 'Darwin' || ! is_dir('/proc')) {
            $this->markTestSkipped(
                'Testing process names is only performed on *nix systems (with the exception of MacOS)'
            );
        }

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger,
            'test' // Process name
        );

        $pid = posix_getpid();
        $swooleServer = $this->createMock(SwooleHttpServer::class);
        $swooleServer->master_pid = 55555;
        $swooleServer->manager_pid = $pid;

        $runner->onStart($swooleServer);
        $this->expectOutputString(sprintf(
            "Swoole is running at :0, in %s\n",
            getcwd()
        ));

        $processFile = sprintf('/proc/%d/cmdline', $pid);
        $this->assertTrue(file_exists($processFile));

        $contents = file_get_contents($processFile);
        $this->assertContains('test-master', $contents);
    }

    public function testProcessNameIsUsedToCreateWorkerProcessNameOnWorkerStart()
    {
        if (\PHP_OS === 'Darwin' || ! is_dir('/proc')) {
            $this->markTestSkipped(
                'Testing process names is only performed on *nix systems (with the exception of MacOS)'
            );
        }

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger,
            'test' // Process name
        );

        $pid = posix_getpid();

        $swooleServer = $this->createMock(SwooleHttpServer::class);
        $swooleServer->setting = [
            'worker_num' => $pid + 1,
        ];

        $runner->onWorkerStart($swooleServer, $pid);
        $this->expectOutputString(sprintf(
            "Worker started in %s with ID %d\n",
            getcwd(),
            $pid
        ));

        $processFile = sprintf('/proc/%d/cmdline', $pid);
        $this->assertTrue(file_exists($processFile));

        $contents = file_get_contents($processFile);
        $this->assertContains('test-worker', $contents);
    }

    public function testProcessNameIsUsedToCreateTaskWorkerProcessNameOnWorkerStart()
    {
        if (\PHP_OS === 'Darwin' || ! is_dir('/proc')) {
            $this->markTestSkipped(
                'Testing process names is only performed on *nix systems (with the exception of MacOS)'
            );
        }

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger,
            'test' // Process name
        );

        $pid = posix_getpid();

        $swooleServer = $this->createMock(SwooleHttpServer::class);
        $swooleServer->setting = [
            'worker_num' => $pid - 2,
        ];

        $runner->onWorkerStart($swooleServer, $pid);
        $this->expectOutputString(sprintf(
            "Worker started in %s with ID %d\n",
            getcwd(),
            $pid
        ));

        $processFile = sprintf('/proc/%d/cmdline', $pid);
        $this->assertTrue(file_exists($processFile));

        $contents = file_get_contents($processFile);
        $this->assertContains('test-task-worker', $contents);
    }

    public function testHotCodeReloaderTriggeredOnWorkerStart()
    {
        $hotCodeReloader = $this->createMock(Reloader::class);
        $hotCodeReloader
            ->expects(static::once())
            ->method('onWorkerStart')
            ->with($this->httpServer, 0);

        $runner = new SwooleRequestHandlerRunner(
            $this->requestHandler->reveal(),
            $this->serverRequestFactory,
            $this->serverRequestError,
            $this->pidManager->reveal(),
            $this->httpServer,
            $this->staticResourceHandler->reveal(),
            $this->logger,
            SwooleRequestHandlerRunner::DEFAULT_PROCESS_NAME,
            $hotCodeReloader
        );
        $runner->onWorkerStart($this->httpServer, 0);
    }
}
