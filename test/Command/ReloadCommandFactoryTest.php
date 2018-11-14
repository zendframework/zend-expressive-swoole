<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Command;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Swoole\Command\ReloadCommand;
use Zend\Expressive\Swoole\Command\ReloadCommandFactory;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;

class ReloadCommandFactoryTest extends TestCase
{
    public function testFactoryUsesDefaultsToCreateCommandWhenNoConfigPresent()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(false);
        $container->get('config')->shouldNotBeCalled();

        $factory = new ReloadCommandFactory();

        $command = $factory($container->reveal());

        $this->assertInstanceOf(ReloadCommand::class, $command);
    }

    public function configProvider() : iterable
    {
        yield 'empty' => [
            [],
            SWOOLE_BASE
        ];

        yield 'populated' => [
            ['zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'mode' => SWOOLE_PROCESS,
                ],
            ]],
            SWOOLE_PROCESS
        ];
    }

    /**
     * @dataProvider configProvider
     */
    public function testFactoryUsesConfigToCreateCommandWhenPresent(array $config, int $mode)
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn($config);

        $factory = new ReloadCommandFactory();

        $command = $factory($container->reveal());

        $this->assertInstanceOf(ReloadCommand::class, $command);
        $this->assertAttributeSame($mode, 'serverMode', $command);
    }
}
