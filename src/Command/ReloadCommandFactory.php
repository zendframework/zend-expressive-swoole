<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Psr\Container\ContainerInterface;

use const SWOOLE_BASE;

class ReloadCommandFactory
{
    public function __invoke(ContainerInterface $container) : ReloadCommand
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $mode   = $config['zend-expressive-swoole']['swoole-http-server']['mode'] ?? SWOOLE_BASE;

        return new ReloadCommand($mode);
    }
}
