<?php

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

    public function testInvoke()
    {
        $factory = $this->pidManagerFactory;
        $pidManager = $factory($this->container->reveal());
        $this->assertInstanceOf(PidManager::class, $pidManager);
    }
}
