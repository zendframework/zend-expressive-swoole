<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use DateTimeImmutable;
use Swoole\Http\Request;

use function filemtime;
use function gmstrftime;
use function preg_match;
use function trim;

class LastModifiedMiddleware implements MiddlewareInterface
{
    use ValidateRegexTrait;

    /**
     * @var string[]
     */
    private $lastModifiedDirectives = [];

    /**
     * @var string[] Array of regexex indicating paths/file types that should
     *     emit a Last-Modified header.
     */
    public function __construct(array $lastModifiedDirectives = [])
    {
        $this->validateRegexList($lastModifiedDirectives, 'Last-Modified');
        $this->lastModifiedDirectives = $lastModifiedDirectives;
    }

    public function __invoke(Request $request, string $filename, callable $next) : StaticResourceResponse
    {
        $response = $next($request, $filename);

        if (! $this->getLastModifiedFlagForPath($request->server['request_uri'])) {
            return $response;
        }

        $lastModified = filemtime($filename) ?? 0;
        $formattedLastModified = trim(gmstrftime('%A %d-%b-%y %T %Z', $lastModified));

        $response->addHeader('Last-Modified', $formattedLastModified);

        if ($this->isUnmodified($request, $formattedLastModified)) {
            $response->setStatus(304);
            $response->disableContent();
        }

        return $response;
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

    /**
     * @return bool Returns true if the If-Modified-Since request header matches
     *     the $lastModifiedTime value; in such cases, no content is returned.
     */
    private function isUnmodified(Request $request, string $lastModified) : bool
    {
        $ifModifiedSince = $request->header['if-modified-since'] ?? '';
        if ('' === $ifModifiedSince) {
            return false;
        }

        if (new DateTimeImmutable($ifModifiedSince) < new DateTimeImmutable($lastModified)) {
            return false;
        }

        return true;
    }
}
