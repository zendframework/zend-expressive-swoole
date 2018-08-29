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

use function is_callable;
use function is_dir;

class StaticResourceHandler implements StaticResourceHandlerInterface
{
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
     * @throws Exception\InvalidStaticResourceMiddlewareException for any
     *     non-callable middleware encountered.
     */
    public function __construct(
        string $docRoot,
        array $middleware = []
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
    }

    public function processStaticResource(
        SwooleHttpRequest $request,
        SwooleHttpResponse $response
    ) : ?StaticResourceHandler\StaticResourceResponse {
        $filename = $this->docRoot . $request->server['request_uri'];

        $middleware = new StaticResourceHandler\MiddlewareQueue($this->middleware);
        $staticResourceResponse = $middleware($request, $filename);
        if ($staticResourceResponse->isFailure()) {
            return null;
        }

        $staticResourceResponse->sendSwooleResponse($response, $filename);
        return $staticResourceResponse;
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
}
