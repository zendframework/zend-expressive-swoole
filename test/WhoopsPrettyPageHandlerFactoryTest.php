<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Whoops\Handler\PrettyPageHandler;
use Zend\Expressive\Swoole\WhoopsPrettyPageHandlerFactory;

class WhoopsPrettyPageHandlerFactoryTest extends TestCase
{
    /** @var WhoopsPrettyPageHandlerFactory */
    private $factory;

    /** @var ObjectProphecy|ContainerInterface */
    private $container;

    public function setUp()
    {
        parent::setUp();
        $this->factory = new WhoopsPrettyPageHandlerFactory();
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testFactoryReturnsPageHandlerWithUnconditionalExceptionHandling() : void
    {
        $this->container->has('Zend\Expressive\WhoopsPageHandler')
            ->willReturn(false);

        /** @var PrettyPageHandler $handler */
        $handler = ($this->factory)($this->container->reveal());
        $this->assertTrue($handler->handleUnconditionally());
    }
}
