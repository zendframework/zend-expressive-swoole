<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Command;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Swoole\Command\StopCommand;
use Zend\Expressive\Swoole\Command\StopCommandFactory;
use Zend\Expressive\Swoole\PidManager;

class StopCommandFactoryTest extends TestCase
{
    public function testFactoryProducesCommand()
    {
        $pidManager = $this->prophesize(PidManager::class)->reveal();
        $container  = $this->prophesize(ContainerInterface::class);
        $container->get(PidManager::class)->willReturn($pidManager);

        $factory = new StopCommandFactory();

        $command = $factory($container->reveal());

        $this->assertInstanceOf(StopCommand::class, $command);
        $this->assertAttributeSame($pidManager, 'pidManager', $command);
    }
}
