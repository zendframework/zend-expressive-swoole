<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process as SwooleProcess;
use Throwable;
use Zend\Expressive\Swoole\Exception;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

use function date;
use function microtime;
use function time;
use function usleep;
use function swoole_set_process_name;
use const PHP_OS;

/**
 * "Run" a request handler using Swoole.
 *
 * The RequestHandlerRunner will marshal a request using the composed factory, and
 * then pass the request to the composed handler. Finally, it emits the response
 * returned by the handler using the Swoole emitter.
 *
 * If the factory for generating the request raises an exception or throwable,
 * then the runner will use the composed error response generator to generate a
 * response, based on the exception or throwable raised.
 */
class SwooleRequestHandlerRunner extends RequestHandlerRunner
{
    /**
     * Default Process Name
     */
    public const DEFAULT_PROCESS_NAME = 'expressive';

    /**
     * Keep CWD in daemon mode.
     *
     * @var string
     */
    private $cwd;

    /**
     * A request handler to run as the application.
     *
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * @var Log\AccessLogInterface
     */
    private $logger;

    /**
     * A manager to handle pid about the application.
     *
     * @var PidManager
     */
    private $pidManager;

    /**
     * Swoole HTTP Server Instance
     *
     * @var SwooleHttpServer
     */
    private $httpServer;

    /**
     * A factory capable of generating an error response in the scenario that
     * the $serverRequestFactory raises an exception during generation of the
     * request instance.
     *
     * The factory will receive the Throwable or Exception that caused the error,
     * and must return a Psr\Http\Message\ResponseInterface instance.
     *
     * @var callable
     */
    private $serverRequestErrorResponseGenerator;

    /**
     * A factory capable of generating a Psr\Http\Message\ServerRequestInterface instance.
     * The factory will not receive any arguments.
     *
     * @var callable
     */
    private $serverRequestFactory;

    /**
     * @var ?StaticResourceHandlerInterface
     */
    private $staticResourceHandler;

    /**
     * @var string
     */
    private $processName;

    public function __construct(
        RequestHandlerInterface $handler,
        callable $serverRequestFactory,
        callable $serverRequestErrorResponseGenerator,
        PidManager $pidManager,
        SwooleHttpServer $httpServer,
        StaticResourceHandlerInterface $staticResourceHandler = null,
        Log\AccessLogInterface $logger = null,
        string $processName = self::DEFAULT_PROCESS_NAME
    ) {
        $this->handler = $handler;

        // Factories are cast as Closures to ensure return type safety.
        $this->serverRequestFactory = function ($request) use ($serverRequestFactory) : ServerRequestInterface {
            return $serverRequestFactory($request);
        };

        $this->serverRequestErrorResponseGenerator =
            function (Throwable $exception) use ($serverRequestErrorResponseGenerator) : ResponseInterface {
                return $serverRequestErrorResponseGenerator($exception);
            };

        // The HTTP server should not yet be running
        if ($httpServer->master_pid > 0 || $httpServer->manager_pid > 0) {
            throw new Exception\InvalidArgumentException('The Swoole server has already been started');
        }
        $this->httpServer = $httpServer;
        $this->pidManager = $pidManager;
        $this->staticResourceHandler = $staticResourceHandler;
        $this->logger = $logger ?: new Log\Psr3AccessLogDecorator(
            new Log\StdoutLogger(),
            new Log\AccessLogFormatter()
        );
        $this->processName = $processName;
        $this->cwd = getcwd();
    }

    /**
     * Run the application
     *
     * Determines which action was requested from the command line, and then
     * executes the task associated with it. If no action was provided, it
     * assumes "start".
     */
    public function run() : void
    {
        $this->httpServer->on('start', [$this, 'onStart']);
        $this->httpServer->on('workerstart', [$this, 'onWorkerStart']);
        $this->httpServer->on('request', [$this, 'onRequest']);
        $this->httpServer->on('shutdown', [$this, 'onShutdown']);
        $this->httpServer->start();
    }

    /**
     * Handle a start event for swoole HTTP server manager process.
     *
     * Writes the master and manager PID values to the PidManager, and ensures
     * the manager and/or workers use the same PWD as the master process.
     */
    public function onStart(SwooleHttpServer $server) : void
    {
        $this->pidManager->write($server->master_pid, $server->manager_pid);

        // Reset CWD
        chdir($this->cwd);
        $this->setProcessName(sprintf('%s-master', $this->processName));

        $this->logger->notice('Swoole is running at {host}:{port}, in {cwd}', [
            'host' => $server->host,
            'port' => $server->port,
            'cwd'  => getcwd(),
        ]);
    }

    /**
     * Handle a workerstart event for swoole HTTP server worker process
     *
     * Ensures workers all use the same PWD as the master process.
     */
    public function onWorkerStart(SwooleHttpServer $server, int $workerId) : void
    {
        // Reset CWD
        chdir($this->cwd);
        if ($workerId >= $server->setting['worker_num']) {
            $this->setProcessName(sprintf('%s-task-worker-%d', $this->processName, $workerId));
        } else {
            $this->setProcessName(sprintf('%s-worker-%d', $this->processName, $workerId));
        }

        $this->logger->notice('Worker started in {cwd} with ID {pid}', [
            'cwd' => getcwd(),
            'pid' => $workerId,
        ]);
    }

    /**
     * Handle an incoming HTTP request
     */
    public function onRequest(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) : void {
        $staticResourceResponse = $this->staticResourceHandler
            ? $this->staticResourceHandler->processStaticResource($request, $response)
            : null;
        if ($staticResourceResponse) {
            // Eventually: emit a request log here
            $this->logger->logAccessForStaticResource($request, $staticResourceResponse);
            return;
        }

        $emitter = new SwooleEmitter($response);

        try {
            $psr7Request = ($this->serverRequestFactory)($request);
        } catch (Throwable $e) {
            // Error in generating the request
            $this->emitMarshalServerRequestException($emitter, $e, $request);
            return;
        }

        $psr7Response = $this->handler->handle($psr7Request);
        $emitter->emit($psr7Response);
        $this->logger->logAccessForPsr7Resource($request, $psr7Response);
    }

     /**
     * Handle the shutting down of the server
     */
    public function onShutdown(SwooleHttpServer $server) : void
    {
        $this->pidManager->delete();
        $this->logger->notice('Swoole HTTP has been terminated.');
    }

    /**
     * Emit marshal server request exception
     */
    private function emitMarshalServerRequestException(
        EmitterInterface $emitter,
        Throwable $exception,
        SwooleHttpRequest $request
    ) : void {
        $psr7Response = ($this->serverRequestErrorResponseGenerator)($exception);
        $emitter->emit($psr7Response);
        $this->logger->logAccessForPsr7Resource($request, $psr7Response);
    }

    /**
     * Set the process name, only if the current OS supports the operation
     * @param string $name
     */
    private function setProcessName(string $name) : void
    {
        if (PHP_OS === 'Darwin') {
            return;
        }
        swoole_set_process_name($name);
    }
}
