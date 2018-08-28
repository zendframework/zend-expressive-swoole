<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Exception;

use function gettype;
use function get_class;
use function is_object;
use function sprintf;

class InvalidStaticResourceMiddlewareException extends InvalidArgumentException
{
    public static function forMiddlewareAtPosition($middleware, $position) : self
    {
        return new self(sprintf(
            'Static resource middleware must be callable; received middleware of type "%s" in position %s',
            is_object($middleware) ? get_class($middleware) : gettype($middleware),
            $position
        ));
    }
}
