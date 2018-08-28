<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;

use function array_shift;

/**
 * @internal
 */
class MiddlewareQueue
{
    /**
     * @var MiddlewareInterface[]
     */
    private $middleware;

    public function __construct(array $middleware)
    {
        $this->middleware = $middleware;
    }

    public function __invoke(Request $request, string $filename) : StaticResourceResponse
    {
        if ([] === $this->middleware) {
            return new StaticResourceResponse();
        }

        $middleware = array_shift($this->middleware);
        return $middleware($request, $filename, $this);
    }
}
