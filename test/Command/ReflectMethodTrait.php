<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Command;

use ReflectionMethod;
use Symfony\Component\Console\Command\Command;

trait ReflectMethodTrait
{
    public function reflectMethod(Command $command, string $method) : ReflectionMethod
    {
        $r = new ReflectionMethod($command, $method);
        $r->setAccessible(true);
        return $r;
    }
}
