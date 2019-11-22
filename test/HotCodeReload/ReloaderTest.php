<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\HotCodeReload;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Swoole\Server as SwooleServer;
use Zend\Expressive\Swoole\HotCodeReload\FileWatcherInterface;
use Zend\Expressive\Swoole\HotCodeReload\Reloader;

class ReloaderTest extends TestCase
{
    /** @var MockObject|FileWatcherInterface */
    private $fileWatcher;

    /** @var int */
    private $interval = 123;

    /** @var Reloader */
    private $subject;

    protected function setUp() : void
    {
        $this->fileWatcher = $this->createMock(FileWatcherInterface::class);
        $this->subject = new Reloader($this->fileWatcher, new NullLogger(), $this->interval);

        parent::setUp();
    }

    /**
     * Creates a constraint that checks if every value is a unique scalar value.
     * Uniqueness is checked by adding values as an array key until a repeat occurs.
     */
    private static function isUniqueScalar() : Constraint
    {
        return new class extends Constraint
        {
            private $values = [];

            protected function matches($other) : bool
            {
                if (isset($this->values[$other])) {
                    return false;
                }

                return $this->values[$other] = true;
            }

            public function toString() : string
            {
                return 'is only used once';
            }
        };
    }

    public function testOnWorkerStartOnlyRegistersTickFunctionOnFirstServer() : void
    {
        $server0 = $this->createMock(SwooleServer::class);
        $server0
            ->expects(static::once())
            ->method('tick')
            ->with(
                $this->interval,
                static::callback(function (callable $callback) use ($server0) {
                    $callback($server0);

                    return true;
                })
            );

        $server1 = $this->createMock(SwooleServer::class);
        $server1
            ->expects(static::never())
            ->method('tick');

        $this->subject->onWorkerStart($server0, 0);
        $this->subject->onWorkerStart($server1, 1);
    }

    public function testIncludedFilesAreOnlyAddedToWatchOnce() : void
    {
        $this->fileWatcher
            ->expects(static::atLeastOnce())
            ->method('addFilePath')
            ->with(static::isUniqueScalar());

        $server = $this->createMock(SwooleServer::class);
        $server->expects(static::never())->method('reload');
        $this->subject->onTick($server);
        $this->subject->onTick($server);
    }

    public function testServerReloadedWhenFilesChange() : void
    {
        $this->fileWatcher
            ->expects(static::once())
            ->method('readChangedFilePaths')
            ->willReturn([
                '/foo.php',
                '/bar.php',
            ]);

        $server = $this->createMock(SwooleServer::class);
        $server
            ->expects(static::once())
            ->method('reload');

        $this->subject->onTick($server);
    }
}
