<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use DateTimeImmutable;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;

use function array_walk;
use function clearstatcache;
use function dechex;
use function explode;
use function filemtime;
use function filesize;
use function file_get_contents;
use function gmstrftime;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function md5_file;
use function pathinfo;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function trim;

use const E_WARNING;
use const PATHINFO_EXTENSION;

class StaticResourceHandler implements StaticResourceHandlerInterface
{
    /**
     * @var string[] Valid Cache-Control directives
     */
    public const CACHECONTROL_DIRECTIVES = [
        'must-revalidate',
        'no-cache',
        'no-store',
        'no-transform',
        'public',
        'private',
    ];

    /**
     * ETag validation type
     */
    public const ETAG_VALIDATION_STRONG = 'strong';
    public const ETAG_VALIDATION_WEAK = 'weak';

    /**
     * @var string[]
     */
    private $allowedETagValidationTypes = [
        self::ETAG_VALIDATION_STRONG,
        self::ETAG_VALIDATION_WEAK,
    ];

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
     * @var array[string, string[]] Key is a regexp; if a static resource path
     *     matches the regexp, the array of values provided will be used as
     *     the Cache-Control header value.
     */
    private $cacheControlDirectives;

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
     * @var string[] Array of regexp; if a path matches a regexp, an ETag will
     *     be emitted for the static file resource.
     */
    private $etagDirectives;

    /**
     * ETag validation type, 'weak' means Weak Validation, 'strong' means Strong Validation,
     * other value will not response ETag header.
     *
     * @var string
     */
    private $etagValidationType;

    /**
     * @var string[] Array of regexp; if a path matches a regexp, a Last-Modified
     *     header will be emitted for the static file resource.
     */
    private $lastModifiedDirectives;

    /**
     * @var array[string, string] Extension => mimetype map
     */
    private $typeMap;

    public function __construct(
        string $docRoot,
        array $typeMap = null,
        array $cacheControlDirectives = [],
        array $lastModifiedDirectives = [],
        array $etagDirectives = [],
        string $etagValidationType = self::ETAG_VALIDATION_WEAK
    ) {
        if (! is_dir($docRoot)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'The document root "%s" does not exist; please check your configuration.',
                $docRoot
            ));
        }

        $this->validateCacheControlDirectives($cacheControlDirectives);
        $this->validateRegexList($lastModifiedDirectives, 'Last-Modified');
        $this->validateRegexList($etagDirectives, 'ETag');
        if (! in_array($etagValidationType, $this->allowedETagValidationTypes, true)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'ETag validation type must be one of [%s]; received "%s"',
                implode(', ', $this->allowedETagValidationTypes),
                $etagValidationType
            ));
        }

        $this->docRoot = $docRoot;
        $this->typeMap = null === $typeMap ? self::TYPE_MAP_DEFAULT : $typeMap;
        $this->cacheControlDirectives = $cacheControlDirectives;
        $this->lastModifiedDirectives = $lastModifiedDirectives;
        $this->etagDirectives = $etagDirectives;
        $this->etagValidationType = $etagValidationType;
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
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        if (! isset($this->cacheTypeFile[$filename])) {
            // fail-safe, in case isStaticResource() was not called first
            return;
        }

        // Method Not Allowed
        if (! in_array($server['request_method'], ['GET', 'HEAD', 'OPTIONS'], true)) {
            $response->header('Allow', 'GET, HEAD, OPTIONS', true);
            $response->status(405);
            $response->end();
            return;
        }

        $response->header('Content-Type', $this->cacheTypeFile[$filename], true);

        $isHeadRequest = $server['request_method'] === 'HEAD';
        $isOptionsRequest = $server['request_method'] === 'OPTIONS';
        if ($isOptionsRequest) {
            $response->header('Allow', 'GET, HEAD, OPTIONS', true);
        }

        $cacheControl = $this->getCacheControlForPath($server['request_uri']);
        if ($cacheControl) {
            $response->header('Cache-Control', $cacheControl, true);
        }

        $emitLastModified = $this->getLastModifiedFlagForPath($server['request_uri']);
        $emitETag = $this->getETagFlagForPath($server['request_uri']);

        if (! $emitLastModified && ! $emitETag) {
            $isHeadRequest || $isOptionsRequest ? $response->end() : $response->sendfile($filename);
            return;
        }

        clearstatcache();
        $lastModifiedTime = filemtime($filename) ?? 0;
        $lastModifiedTimeFormatted = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModifiedTime));

        if ($emitLastModified) {
            $response->header('Last-Modified', $lastModifiedTimeFormatted, true);
        }

        if ($emitETag && $this->prepareAndEmitEtag($request, $response, $filename, $lastModifiedTime)) {
            return;
        }

        if ($emitLastModified && $this->isUnmodified($request, $response, $lastModifiedTimeFormatted)) {
            return;
        }

        $isHeadRequest || $isOptionsRequest ? $response->end() : $response->sendfile($filename);
    }

    /**
     * @throws Exception\InvalidArgumentException if any Cache-Control regex is invalid
     * @throws Exception\InvalidArgumentException if any individual directive
     *     associated with a regex is invalid.
     */
    private function validateCacheControlDirectives(array $cacheControlDirectives) : void
    {
        foreach ($cacheControlDirectives as $regex => $directives) {
            if (! $this->isValidRegex($regex)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'The Cache-Control regex "%s" is invalid',
                    $regex
                ));
            }

            if (! is_array($directives)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'The Cache-Control directives associated with the regex "%s" are invalid;'
                    . ' each must be an array of strings',
                    $regex
                ));
            }

            array_walk($directives, function ($directive) use ($regex) {
                if (! is_string($directive)) {
                    throw new Exception\InvalidArgumentException(sprintf(
                        'One or more Cache-Control directives associated with the regex "%s" are invalid;'
                        . ' each must be a string',
                        $regex
                    ));
                }
                $this->validateCacheControlDirective($regex, $directive);
            });
        }
    }

    /**
     * @throws Exception\InvalidArgumentException if any regexp is invalid
     */
    private function validateCacheControlDirective(string $regex, string $directive) : void
    {
        if (in_array($directive, self::CACHECONTROL_DIRECTIVES, true)) {
            return;
        }
        if (preg_match('/^max-age=\d+$/', $directive)) {
            return;
        }
        throw new Exception\InvalidArgumentException(sprintf(
            'The Cache-Control directive "%s" associated with regex "%s" is invalid.'
            . ' Must be one of [%s] or match /^max-age=\d+$/',
            $directive,
            $regex,
            implode(', ', self::CACHECONTROL_DIRECTIVES)
        ));
    }

    /**
     * @throws Exception\InvalidArgumentException if any regexp is invalid
     */
    private function validateRegexList(array $regexList, string $type) : void
    {
        foreach ($regexList as $regex) {
            if (! $this->isValidRegex($regex)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'The %s regex "%s" is invalid',
                    $type,
                    $regex
                ));
            }
        }
    }

    private function isValidRegex(string $regex) : bool
    {
        set_error_handler(function ($errno) {
            return $errno === E_WARNING;
        });
        $isValid = preg_match($regex, '') !== false;
        restore_error_handler();
        return $isValid;
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

    /**
     * @return null|string Returns null if the path does not have any
     *     associated cache-control directives; otherwise, it will
     *     return a string representing the entire Cache-Control
     *     header value to emit.
     */
    private function getCacheControlForPath(string $path) : ?string
    {
        foreach ($this->cacheControlDirectives as $regexp => $values) {
            if (preg_match($regexp, $path)) {
                return implode(', ', $values);
            }
        }
        return null;
    }

    private function getLastModifiedFlagForPath(string $path) : bool
    {
        foreach ($this->lastModifiedDirectives as $regexp) {
            if (preg_match($regexp, $path)) {
                return true;
            }
        }
        return false;
    }

    private function getETagFlagForPath(string $path) : bool
    {
        foreach ($this->etagDirectives as $regexp) {
            if (preg_match($regexp, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool Returns true if the request issued an if-match and/or
     *     if-none-match header with a matching ETag; in such cases, a 304
     *     status is emitted with no content. Boolean false indicates the file
     *     content should be provided, assuming other conditions require it as
     *     well.
     */
    private function prepareAndEmitEtag(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response,
        string $filename,
        int $lastModifiedTime
    ) : bool {
        $etag = '';
        switch ($this->etagValidationType) {
            case self::ETAG_VALIDATION_WEAK:
                $filesize = filesize($filename) ?? 0;
                if (! $lastModifiedTime || ! $filesize) {
                    return false;
                }
                $etag = sprintf('W/"%x-%x"', $lastModifiedTime, $filesize);
                break;
            case self::ETAG_VALIDATION_STRONG:
                $etag = md5_file($filename);
                break;
            default:
                return false;
        }
        $response->header('ETag', $etag, true);

        // Determine if ETag the client expects matches calculated ETag
        $ifMatch = $request->header['if-match'] ?? '';
        $ifNoneMatch = $request->header['if-none-match'] ?? '';
        $clientEtags = explode(',', $ifMatch ?: $ifNoneMatch);
        array_walk($clientEtags, 'trim');

        if (in_array($etag, $clientEtags, true)) {
            $response->status(304);
            $response->end();
            return true;
        }

        return false;
    }

    /**
     * @return bool Returns true if the If-Modified-Since request header matches
     *     the $lastModifiedTime value; in such cases, no content is returned.
     */
    private function isUnmodified(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response,
        string $lastModifiedTime
    ) : bool {
        $ifModifiedSince = $request->header['if-modified-since'] ?? '';
        if ('' === $ifModifiedSince) {
            return false;
        }

        if (new DateTimeImmutable($ifModifiedSince) < new DateTimeImmutable($lastModifiedTime)) {
            return false;
        }

        $response->status(304);
        $response->end();
        return true;
    }
}
