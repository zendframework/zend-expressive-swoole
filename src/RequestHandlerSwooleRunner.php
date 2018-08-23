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
use Zend\Console\Getopt;
use Zend\Expressive\Swoole\Exception;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

use function file_exists;
use function function_exists;
use function gzcompress;
use function pathinfo;
use function sprintf;

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
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types
     */
    public const DEFAULT_STATIC_EXTS = [
        '7z'    => 'application/x-7z-compressed',
        'aac'   => 'audio/aac',
        'arc'   => 'application/octet-stream',
        'avi'   => 'video/x-msvideo',
        'azw'   => 'application/vnd.amazon.ebook',
        'bin'   => 'application/octet-stream',
        'bmp'   => 'image/bmp',
        'bz'    => 'application/x-bzip',
        'bz2'   => 'application/x-bzip2',
        'css'   => 'text/css',
        'csv'   => 'text/csv',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'eot'   => 'application/vnd.ms-fontobject',
        'epub'  => 'application/epub+zip',
        'es'    => 'application/ecmascript',
        'gif'   => 'image/gif',
        'htm'   => 'text/html',
        'html'  => 'text/html',
        'ico'   => 'image/x-icon',
        'jpg'   => 'image/jpg',
        'jpeg'  => 'image/jpg',
        'js'    => 'text/javascript',
        'json'  => 'application/json',
        'mp4'   => 'video/mp4',
        'mpeg'  => 'video/mpeg',
        'odp'   => 'application/vnd.oasis.opendocument.presentation',
        'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt'   => 'application/vnd.oasis.opendocument.text',
        'oga'   => 'audio/ogg',
        'ogv'   => 'video/ogg',
        'ogx'   => 'application/ogg',
        'otf'   => 'font/otf',
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        'ppt'   => 'application/vnd.ms-powerpoint',
        'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'rar'   => 'application/x-rar-compressed',
        'rtf'   => 'application/rtf',
        'svg'   => 'image/svg+xml',
        'swf'   => 'application/x-shockwave-flash',
        'tar'   => 'application/x-tar',
        'tif'   => 'image/tiff',
        'tiff'  => 'image/tiff',
        'ts'    => 'application/typescript',
        'ttf'   => 'font/ttf',
        'txt'   => 'text/plain',
        'wav'   => 'audio/wav',
        'weba'  => 'audio/webm',
        'webm'  => 'video/webm',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'xhtml' => 'application/xhtml+xml',
        'xls'   => 'application/vnd.ms-excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xml'   => 'application/xml',
        'xul'   => 'application/vnd.mozilla.xul+xml',
        'zip'   => 'application/zip',
    ];

    /**
     * @var array
     */
    private $allowedStatic;

    /**
     * Cache the file extensions (type) for valid static file
     *
     * @var array
     */
    private $cacheTypeFile = [];

    /**
     * @var string
     */
    private $docRoot;

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
     * @throws Exception\InvalidConfigException if the configured or default
     *     document root does not exist.
     */
    public function __construct(
        RequestHandlerInterface $handler,
        callable $serverRequestFactory,
        callable $serverRequestErrorResponseGenerator,
        ServerFactory $serverFactory,
        array $config,
        LoggerInterface $logger = null,
        PidManager $pidManager
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

        $this->gzip = (int)($config['gzip']['level'] ?? 0);

        $this->allowedStatic = $config['static_files'] ?? self::DEFAULT_STATIC_EXTS;
        $this->docRoot = $config['options']['document_root'] ?? getcwd() . '/public';
        if (! file_exists($this->docRoot)) {
            throw new Exception\InvalidConfigException(sprintf(
                'The document_root %s does not exist. Please check your configuration.',
                $this->docRoot
            ));
        }

        $this->logger = $logger ?: new StdoutLogger();
        $this->pidManager = $pidManager;
    }

    /**
     * Run the application
     */
    public function run() : void
    {
        $opts = new Getopt([
            'd|daemonize'  => 'Daemonize the swoole server process',
            'n|num_workers=i' => 'The number of the worker processes to start.',
        ]);
        $args = $opts->getArguments();
        $action = $args[0] ?? null;
        switch ($action) {
            case 'stop':
                $this->stopServer();
                break;
            case 'start':
            default:
                $this->startServer([
                    'daemonize' => $opts->getOption('daemonize') ? (bool) $opts->getOption('daemonize') : false,
                    'worker_num' => $opts->getOption('num_workers') ? (int) $opts->getOption('num_workers') : 1,
                ]);
                break;
        }
    }

    /**
     * Start swoole server
     *
     * @param array $options Swoole server options
     */
    public function startServer(array $options = []) : void
    {
        $swooleServer = $this->serverFactory->createSwooleServer($options);
        $swooleServer->on('start', [$this, 'onStart']);
        $swooleServer->on('request', [$this, 'onRequest']);
        $swooleServer->start();
    }

    /**
     * Stop swoole server
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
     * Is swoole server running ?
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
     * On start event for swoole http server
     */
    public function onStart(SwooleHttpServer $server) : void
    {
        $this->pidManager->write($server->master_pid, $server->manager_pid);
        $this->logger->info('Swoole is running at {host}:{port}', [
            'host' => $server->host,
            'port' => $server->port,
        ]);
    }

    /**
     * On HTTP request event for swoole http server
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

        if ($this->getStaticResource($request, $response)) {
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

    /**
     * Get a static resource, if any, and set the swoole HTTP response.
     */
    private function getStaticResource(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) : bool {
        $staticFile = $this->docRoot . $request->server['request_uri'];
        if (! isset($this->cacheTypeFile[$staticFile])
            && ! $this->cacheFile($staticFile)
        ) {
            return false;
        }

        $response->header('Content-Type', $this->cacheTypeFile[$staticFile]);

        // Handle Gzip
        if ($this->isGzipAvailable($request)) {
            [$contentEncoding, $compressionEncoding] = $this->getCompressionEncoding($request);
            if ($contentEncoding && $compressionEncoding) {
                $response->header('Content-Encoding', $contentEncoding, true);
                $response->header('Connection', 'close', true);
                $handle = fopen($staticFile, 'rb');
                $params = ['level' => $this->gzip, 'window' => $compressionEncoding, 'memory' => 9];
                stream_filter_append($handle, 'zlib.deflate', STREAM_FILTER_READ, $params);
                while (feof($handle) !== true) {
                    $response->write(fgets($handle, 4096));
                }
                fclose($handle);
                $response->end();
                return true;
            }
        }

        $response->sendfile($staticFile);
        return true;
    }

    /**
     * Is gzip available for current request
     */
    private function isGzipAvailable(SwooleHttpRequest $request): bool
    {
        return $this->gzip > 0
            && isset($request->header['accept-encoding']);
    }

    /**
     * Get gzcompress compression encoding
     */
    private function getCompressionEncoding(SwooleHttpRequest $request) : array
    {
        $acceptEncodings = explode(',', $request->header['accept-encoding']);
        foreach ($acceptEncodings ?? [] as $acceptEncoding) {
            $acceptEncoding = trim($acceptEncoding);
            if ('gzip' === $acceptEncoding) {
                return [
                    'gzip',
                    ZLIB_ENCODING_GZIP
                ];
            } elseif ('deflate' === $acceptEncoding) {
                return [
                    'deflate',
                    ZLIB_ENCODING_DEFLATE
                ];
            }
        }
        return [];
    }

    /**
     * Attempt to cache a static file resource.
     */
    private function cacheFile(string $fileName) : bool
    {
        $type = pathinfo($fileName, PATHINFO_EXTENSION);
        if (! isset($this->allowedStatic[$type])) {
            return false;
        }

        if (! file_exists($fileName)) {
            return false;
        }

        $this->cacheTypeFile[$fileName] = $this->allowedStatic[$type];
        return true;
    }
}
