<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\HotCodeReload;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zend\Expressive\Swoole\HotCodeReload\FileWatcherInterface;
use Zend\Expressive\Swoole\HotCodeReload\ReloaderFactory;
use Zend\Expressive\Swoole\Log\StdoutLogger;
use Zend\ServiceManager\ServiceManager;

class ReloaderFactoryTest extends TestCase
{
    /** @var ServiceManager */
    private $container;

    /** @var FileWatcherInterface */
    private $fileWatcher;

    protected function setUp() : void
    {
        $this->fileWatcher = $this->createMock(FileWatcherInterface::class);
        $this->container = new ServiceManager();
        $this->container->setAllowOverride(true);
        $this->container->setService(FileWatcherInterface::class, $this->fileWatcher);

        parent::setUp();
    }

    /**
     * @dataProvider provideServiceManagerServicesWithEmptyConfigurations
     */
    public function testCreateUnconfigured(array $services) : void
    {
        $this->container->configure(['services' => $services]);
        $reloader = (new ReloaderFactory())->__invoke($this->container);

        static::assertAttributeSame($this->fileWatcher, 'fileWatcher', $reloader);
        static::assertAttributeEquals(new StdoutLogger(), 'logger', $reloader);
        static::assertAttributeSame(500, 'interval', $reloader);
    }

    public function testCreateConfigured() : void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->configure([
            'services' => [
                'config' => [
                    'zend-expressive-swoole' => [
                        'hot-code-reload' => [
                            'interval' => 999,
                        ],
                    ],
                ],
                LoggerInterface::class => $logger,
            ],
        ]);
        $reloader = (new ReloaderFactory())->__invoke($this->container);

        static::assertAttributeSame($this->fileWatcher, 'fileWatcher', $reloader);
        static::assertAttributeSame(999, 'interval', $reloader);
        static::assertAttributeSame($logger, 'logger', $reloader);
    }

    public function provideServiceManagerServicesWithEmptyConfigurations() : iterable
    {
        yield 'empty container' => [
            [

            ],
        ];

        yield 'empty config' => [
            [
                'config' => [],
            ],
        ];

        yield 'empty hot-code-reload' => [
            [
                'config' => [
                    'zend-expressive-swoole' => [

                    ],
                ],
            ],
        ];
    }
}
