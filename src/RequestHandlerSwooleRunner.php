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
use Psr\Log\LoggerInterface;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process as SwooleProcess;
use Throwable;
use Zend\Expressive\Swoole\Exception;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

use function date;
use function time;
use function usleep;

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
class RequestHandlerSwooleRunner extends RequestHandlerRunner
{
    /**
     * Keep CWD in daemon mode.
     *
     * @var string
     */
    private $cwd;

    /**
     * Enable the gzip of response content. The range is 0 to 9, the higher the number, the
     * higher the compression level, 0 means disable gzip function.
     *
     * @var int
     */
    private $gzip;

    /**
     * A request handler to run as the application.
     *
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * A manager to handle pid about the application.
     *
     * @var PidManager
     */
    private $pidManager;

    /**
     * Factory for creating an HTTP server instance.
     *
     * @var ServerFactory
     */
    private $serverFactory;

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

    public function __construct(
        RequestHandlerInterface $handler,
        callable $serverRequestFactory,
        callable $serverRequestErrorResponseGenerator,
        PidManager $pidManager,
        ServerFactory $serverFactory,
        StaticResourceHandlerInterface $staticResourceHandler = null,
        LoggerInterface $logger = null
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

        $this->serverFactory = $serverFactory;
        $this->pidManager = $pidManager;
        $this->staticResourceHandler = $staticResourceHandler;
        $this->logger = $logger ?: new StdoutLogger();
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
        $commandLine = new CommandLine();
        $options = $commandLine->parse();
        switch ($options->getAction()) {
            case CommandLineOptions::ACTION_HELP:
                $commandLine->emitHelpAndExit();
                break;
            case CommandLineOptions::ACTION_RELOAD:
                $this->reloadWorker();
                break;
            case CommandLineOptions::ACTION_STOP:
                $this->stopServer();
                break;
            case CommandLineOptions::ACTION_START:
            default:
                $this->startServer([
                    'daemonize' => $options->daemonize(),
                    'worker_num' => $options->getNumberOfWorkers(),
                ]);
                break;
        }
    }

    /**
     * Start the swoole HTTP server
     *
     * @param array $options Swoole server options
     */
    public function startServer(array $options = []) : void
    {
        $swooleServer = $this->serverFactory->createSwooleServer($options);
        $swooleServer->on('start', [$this, 'onStart']);
        $swooleServer->on('workerstart', [$this, 'onWorkerStart']);
        $swooleServer->on('request', [$this, 'onRequest']);
        $swooleServer->start();
    }

    /**
     * Stop the swoole HTTP server
     */
    public function stopServer() : void
    {
        if (! $this->isRunning()) {
            $this->logger->info('Server is not running yet');
            return;
        }
        [$masterPid, ] = $this->pidManager->read();
        $this->logger->info('Server stopping ...');
        $result = SwooleProcess::kill((int) $masterPid);
        $startTime = time();
        while (! $result) {
            if (! SwooleProcess::kill((int) $masterPid, 0)) {
                continue;
            }
            if (time() - $startTime >= 60) {
                $result = false;
                break;
            }
            usleep(10000);
        }
        if (! $result) {
            $this->logger->info('Server stop failure');
            return;
        }
        $this->pidManager->delete();
        $this->logger->info('Server stopped');
    }

    /**
     * Reload all workers
     *
     * Please note: the reload worker action can ONLY run when operating in
     * SWOOLE_PROCESS mode.
     */
    public function reloadWorker() : void
    {
        if (! $this->isRunning()) {
            $this->logger->info('Server is not running yet');
            return;
        }
        [$masterPid, ] = $this->pidManager->read();
        $this->logger->info('Worker reloading ...');
        $result = SwooleProcess::kill((int) $masterPid, SIGUSR1);
        if (! $result) {
            $this->logger->info('Worker reload failure');
            return;
        }
        $this->logger->info('Worker reloaded');
    }

    /**
     * Is the swoole HTTP server running?
     */
    public function isRunning() : bool
    {
        [$masterPid, $managerPid] = $this->pidManager->read();
        if ($managerPid) {
            // Swoole process mode
            return $masterPid && $managerPid && SwooleProcess::kill((int) $managerPid, 0);
        }
        // Swoole base mode, no manager process
        return $masterPid && SwooleProcess::kill((int) $masterPid, 0);
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

        $this->logger->info('Swoole is running at {host}:{port}, in {cwd}', [
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

        $this->logger->info('Worker started in {cwd} with ID {pid}', [
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
        $this->logger->info('{ts} - {remote_addr} - {request_method} {request_uri}', [
            'ts'             => date('Y-m-d H:i:sO', time()),
            'remote_addr'    => $request->server['remote_addr'],
            'request_method' => $request->server['request_method'],
            'request_uri'    => $request->server['request_uri']
        ]);

        if ($this->staticResourceHandler
            && $this->staticResourceHandler->isStaticResource($request)
        ) {
            $this->staticResourceHandler->sendStaticResource($request, $response);
            return;
        }

        $emitter = new SwooleEmitter($response);

        try {
            $psr7Request = ($this->serverRequestFactory)($request);
        } catch (Throwable $e) {
            // Error in generating the request
            $this->emitMarshalServerRequestException($emitter, $e);
            return;
        }

        $emitter->emit($this->handler->handle($psr7Request));
    }

    /**
     * Emit marshal server request exception
     */
    private function emitMarshalServerRequestException(
        EmitterInterface $emitter,
        Throwable $exception
    ) : void {
        $response = ($this->serverRequestErrorResponseGenerator)($exception);
        $emitter->emit($response);
    }
}
