<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;

use function sys_get_temp_dir;

class PidManagerFactory
{
    public function __invoke(ContainerInterface $container) : PidManager
    {
        $config = $container->get('config');
        return new PidManager(
            $config['zend-expressive-swoole']['swoole-http-server']['options']['pid_file']
                ?? sys_get_temp_dir() . '/zend-swoole.pid'
        );
    }
}
