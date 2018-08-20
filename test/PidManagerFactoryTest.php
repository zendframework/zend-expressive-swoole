<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Swoole\PidManager;
use Zend\Expressive\Swoole\PidManagerFactory;

class PidManagerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->pidManagerFactory = new PidManagerFactory();
    }

    public function testFactoryReturnsAPidManager()
    {
        $factory = $this->pidManagerFactory;
        $pidManager = $factory($this->container->reveal());
        $this->assertInstanceOf(PidManager::class, $pidManager);
    }
}
