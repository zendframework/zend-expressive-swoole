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
use Throwable;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

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
     * Default static file extensions supported
     */
    const DEFAULT_STATIC_EXTS = [
        'css'  => 'text/css',
        'js'   => 'text/javascript',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'jpg'  => 'image/jpg',
        'jpeg' => 'image/jpg',
        'mp4'  => 'video/mp4'
    ];

    /**
     * A request handler to run as the application.
     *
     * @var RequestHandlerInterface
     */
    private $handler;

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
     * @var swoole_http_server
     */
    private $swooleHttpServer;

    /**
     * @var array
     */
    private $allowedStatic;

    /**
     * @var string
     */
    private $docRoot;

    /**
     * Cache the file extensions (type) for valid static file
     *
     * @var array
     */
    private $cacheTypeFile = [];

    public function __construct(
        RequestHandlerInterface $handler,
        callable $serverRequestFactory,
        callable $serverRequestErrorResponseGenerator,
        SwooleHttpServer $swooleHttpServer,
        array $config
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

        $this->swooleHttpServer = $swooleHttpServer;

        $this->allowedStatic = $config['static_files'] ?? self::DEFAULT_STATIC_EXTS;
        $this->docRoot = $config['options']['document_root'] ?? getcwd() . '/public';
    }

    /**
     * Run the application
     */
    public function run() : void
    {
        $this->swooleHttpServer->on('start', [$this, 'onStart']);
        $this->swooleHttpServer->on('request', [$this, 'onRequest']);
        $this->swooleHttpServer->start();
    }

    /**
     * On start event for swoole http server
     */
    public function onStart(SwooleHttpServer $server) : void
    {
        printf("Swoole is running at %s:%s\n", $server->host, $server->port);
    }

    /**
     * On HTTP request event for swoole http server
     */
    public function onRequest(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) {
        printf(
            "%s - %s - %s %s\n",
            date('Y-m-d H:i:sO', time()),
            $request->server['remote_addr'],
            $request->server['request_method'],
            $request->server['request_uri']
        );
        if ($this->getStaticResource($request, $response)) {
            return;
        }
        $emit = new SwooleEmitter($response);
        try {
            $psr7Request = ($this->serverRequestFactory)($request);
        } catch (Throwable $e) {
            // Error in generating the request
            $this->emitMarshalServerRequestException($emit, $e);
            return;
        }
        $emit->emit($this->handler->handle($psr7Request));
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

    /**
     * Get a static resource, if any, and set the swoole HTTP response
     */
    private function getStaticResource(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) : bool {
        $staticFile = $this->docRoot . $request->server['request_uri'];
        if (! isset($cacheTypeFile[$staticFile])) {
            if (! file_exists($staticFile)) {
                return false;
            }
            $type = pathinfo($staticFile, PATHINFO_EXTENSION);
            if (! isset($this->allowedStatic[$type])) {
                return false;
            }
            $cacheTypeFile[$staticFile] = $this->allowedStatic[$type];
        }
        $response->header('Content-Type', $cacheTypeFile[$staticFile]);
        $response->sendfile($staticFile);
        return true;
    }
}
