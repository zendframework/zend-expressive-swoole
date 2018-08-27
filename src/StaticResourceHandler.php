<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;

use function file_exists;
use function is_callable;
use function is_dir;
use function pathinfo;

use const PATHINFO_EXTENSION;

class StaticResourceHandler implements StaticResourceHandlerInterface
{
    /**
     * Default static file extensions supported
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types
     */
    public const TYPE_MAP_DEFAULT = [
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
     * Middleware to execute when serving a static resource.
     *
     * @var StaticResourceHandler\MiddlewareInterface[]
     */
    private $middleware;

    /**
     * @var array[string, string] Extension => mimetype map
     */
    private $typeMap;

    /**
     * @throws Exception\InvalidStaticResourceMiddlewareException for any
     *     non-callable middleware encountered.
     */
    public function __construct(
        string $docRoot,
        array $middleware = [],
        array $typeMap = null
    ) {
        if (! is_dir($docRoot)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'The document root "%s" does not exist; please check your configuration.',
                $docRoot
            ));
        }
        $this->validateMiddleware($middleware);

        $this->docRoot = $docRoot;
        $this->middleware = $middleware;
        $this->typeMap = null === $typeMap ? self::TYPE_MAP_DEFAULT : $typeMap;
    }

    public function isStaticResource(SwooleHttpRequest $request) : bool
    {
        $staticFile = $this->docRoot . $request->server['request_uri'];
        return isset($this->cacheTypeFile[$staticFile]) || $this->cacheFile($staticFile);
    }

    public function sendStaticResource(SwooleHttpRequest $request, SwooleHttpResponse $response) : void
    {
        $server   = $request->server;
        $filename = $this->docRoot . $server['request_uri'];
        if (! isset($this->cacheTypeFile[$filename])) {
            // fail-safe, in case isStaticResource() was not called first
            return;
        }

        $middleware = new StaticResourceHandler\MiddlewareQueue($this->middleware);
        $responseValues = $middleware($request, $filename);

        $response->status($responseValues->getStatus());
        $response->header('Content-Type', $this->cacheTypeFile[$filename], true);
        foreach ($responseValues->getHeaders() as $header => $value) {
            $response->header($header, $value, true);
        }

        $responseValues->shouldSendContent()
            ? $response->sendfile($filename)
            : $response->end();
    }

    /**
     * Validate that each middleware provided is callable.
     *
     * @throws Exception\InvalidStaticResourceMiddlewareException for any
     *     non-callable middleware encountered.
     */
    private function validateMiddleware(array $middlewareList) : void
    {
        foreach ($middlewareList as $position => $middleware) {
            if (! is_callable($middleware)) {
                throw Exception\InvalidStaticResourceMiddlewareException::forMiddlewareAtPosition(
                    $middleware,
                    $position
                );
            }
        }
    }

    /**
     * Attempt to cache a static file resource.
     */
    private function cacheFile(string $fileName) : bool
    {
        $type = pathinfo($fileName, PATHINFO_EXTENSION);
        if (! isset($this->typeMap[$type])) {
            return false;
        }

        if (! file_exists($fileName)) {
            return false;
        }

        $this->cacheTypeFile[$fileName] = $this->typeMap[$type];
        return true;
    }
}
