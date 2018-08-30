<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;

use function file_exists;
use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * Filter by content type
 *
 * This middleware uses a map of file extensions to content types. If the
 * requested file has an extension that is not in the list, the middleware
 * returns a failure response.
 *
 * Otherwise, it caches the discovered content-type for the requested file,
 * and emits a Content-Type header on the final response.
 *
 * This middleware should likely execute early in the chain.
 */
class ContentTypeFilterMiddleware implements MiddlewareInterface
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
     * @var array[string, string] Extension => mimetype map
     */
    private $typeMap;

    /**
     * @param null|array[string, string] $typeMap Map of extensions to Content-Type
     *     values. If `null` is provided, the default list in TYPE_MAP_DEFAULT will
     *     be used. Otherwise, the list provided is used verbatim.
     */
    public function __construct(array $typeMap = null)
    {
        $this->typeMap = null === $typeMap ? self::TYPE_MAP_DEFAULT : $typeMap;
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(Request $request, string $filename, callable $next): StaticResourceResponse
    {
        if (! isset($this->cacheTypeFile[$filename])
            && ! $this->cacheFile($filename)
        ) {
            $response = new StaticResourceResponse();
            $response->markAsFailure();
            return $response;
        }

        $response = $next($request, $filename);
        $response->addHeader('Content-Type', $this->cacheTypeFile[$filename]);
        return $response;
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
