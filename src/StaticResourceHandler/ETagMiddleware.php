<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;
use Zend\Expressive\Swoole\Exception;

use function array_walk;
use function explode;
use function filemtime;
use function filesize;
use function implode;
use function in_array;
use function md5_file;
use function preg_match;
use function sprintf;

class ETagMiddleware implements MiddlewareInterface
{
    use ValidateRegexTrait;

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

    public function __construct(array $etagDirectives = [], string $etagValidationType = self::ETAG_VALIDATION_WEAK)
    {
        $this->validateRegexList($etagDirectives, 'ETag');
        if (! in_array($etagValidationType, $this->allowedETagValidationTypes, true)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'ETag validation type must be one of [%s]; received "%s"',
                implode(', ', $this->allowedETagValidationTypes),
                $etagValidationType
            ));
        }

        $this->etagDirectives = $etagDirectives;
        $this->etagValidationType = $etagValidationType;
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(Request $request, string $filename, callable $next) : StaticResourceResponse
    {
        $response = $next($request, $filename);

        if (! $this->getETagFlagForPath($request->server['request_uri'])) {
            return $response;
        }

        return $this->prepareETag($request, $filename, $response);
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
    private function prepareETag(
        Request $request,
        string $filename,
        StaticResourceResponse $response
    ) : StaticResourceResponse {
        $etag = '';
        $lastModified = filemtime($filename) ?? 0;
        switch ($this->etagValidationType) {
            case self::ETAG_VALIDATION_WEAK:
                $filesize = filesize($filename) ?? 0;
                if (! $lastModified || ! $filesize) {
                    return $response;
                }
                $etag = sprintf('W/"%x-%x"', $lastModified, $filesize);
                break;
            case self::ETAG_VALIDATION_STRONG:
                $etag = md5_file($filename);
                break;
            default:
                return $response;
        }

        $response->addHeader('ETag', $etag);

        // Determine if ETag the client expects matches calculated ETag
        $ifMatch = $request->header['if-match'] ?? '';
        $ifNoneMatch = $request->header['if-none-match'] ?? '';
        $clientEtags = explode(',', $ifMatch ?: $ifNoneMatch);
        array_walk($clientEtags, 'trim');

        if (in_array($etag, $clientEtags, true)) {
            $response->setStatus(304);
            $response->disableContent();
        }

        return $response;
    }
}
