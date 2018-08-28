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
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;

class CacheControlMiddleware implements MiddlewareInterface
{
    use ValidateRegexTrait;

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
     * @var array[string, string[]] Key is a regexp; if a static resource path
     *     matches the regexp, the array of values provided will be used as
     *     the Cache-Control header value.
     */
    private $cacheControlDirectives;

    public function __construct(array $cacheControlDirectives = [])
    {
        $this->validateCacheControlDirectives($cacheControlDirectives);
        $this->cacheControlDirectives = $cacheControlDirectives;
    }

    public function __invoke(Request $request, string $filename, callable $next) : StaticResourceResponse
    {
        $response = $next($request, $filename);
        $cacheControl = $this->getCacheControlForPath($request->server['request_uri']);
        if ($cacheControl) {
            $response->addHeader('Cache-Control', $cacheControl);
        }
        return $response;
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
}
