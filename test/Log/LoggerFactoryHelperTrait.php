<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Log;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Zend\Expressive\Swoole\Log\AccessLogFormatterInterface;
use Zend\Expressive\Swoole\Log\SwooleLoggerFactory;

trait LoggerFactoryHelperTrait
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->has(SwooleLoggerFactory::SWOOLE_LOGGER)->willReturn(false);
        $this->logger = $this->prophesize(LoggerInterface::class)->reveal();
        $this->formatter = $this->prophesize(AccessLogFormatterInterface::class)->reveal();
    }

    private function createContainerMockWithNamedLogger() : ContainerInterface
    {
        $this->createContainerMockWithConfigAndNotPsrLogger([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'logger' => [
                        'logger-name' => 'my_logger',
                    ],
                ],
            ],
        ]);
        $this->container->get('my_logger')->willReturn($this->logger);

        return $this->container->reveal();
    }

    private function createContainerMockWithConfigAndPsrLogger(?array $config = null) : ContainerInterface
    {
        $this->registerConfigService($config);
        $this->container->has(LoggerInterface::class)->willReturn(true);
        $this->container->get(LoggerInterface::class)->shouldBeCalled()->willReturn($this->logger);

        return $this->container->reveal();
    }

    private function createContainerMockWithConfigAndNotPsrLogger(?array $config = null) : ContainerInterface
    {
        $this->registerConfigService($config);
        $this->container->has(LoggerInterface::class)->willReturn(false);
        $this->container->get(LoggerInterface::class)->shouldNotBeCalled();

        return $this->container->reveal();
    }

    private function registerConfigService(?array $config = null) : void
    {
        $hasConfig = $config !== null;
        $shouldBeCalledMethod = $hasConfig ? 'shouldBeCalled' : 'shouldNotBeCalled';

        $this->container->has('config')->willReturn($hasConfig);
        $this->container->get('config')->{$shouldBeCalledMethod}()->willReturn($config);
    }
}
