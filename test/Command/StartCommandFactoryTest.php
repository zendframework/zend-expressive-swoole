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
use Zend\Expressive\Swoole\Command\StartCommand;
use Zend\Expressive\Swoole\Command\StartCommandFactory;

class StartCommandFactoryTest extends TestCase
{
    public function testFactoryProducesCommand()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();

        $factory = new StartCommandFactory();

        $command = $factory($container);

        $this->assertInstanceOf(StartCommand::class, $command);
        $this->assertAttributeSame($container, 'container', $command);
    }
}
