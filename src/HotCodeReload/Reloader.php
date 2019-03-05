<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\HotCodeReload;

use Psr\Log\LoggerInterface;
use Swoole\Server as SwooleServer;

use function get_included_files;

class Reloader
{
    /**
     * A file watcher to monitor changes in files.
     *
     * @var FileWatcherInterface
     */
    private $fileWatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $interval;

    /**
     * File paths currently being monitored for changes.
     *
     * @var string[]
     */
    private $watchedFilePaths = [];

    public function __construct(FileWatcherInterface $fileWatcher, LoggerInterface $logger, int $interval)
    {
        $this->fileWatcher = $fileWatcher;
        $this->interval = $interval;
        $this->logger = $logger;
    }

    public function onWorkerStart(SwooleServer $server, int $workerId) : void
    {
        // This method will be called for each started worker.
        // We will register our tick function on the first worker.
        if (0 === $workerId) {
            $server->tick($this->interval, $this->generateTickCallback($server));
        }
    }

    public function onTick(SwooleServer $server) : void
    {
        $this->watchIncludedFiles();
        $changedFilePaths = $this->fileWatcher->readChangedFilePaths();
        if ($changedFilePaths) {
            foreach ($changedFilePaths as $path) {
                $this->logger->notice("Reloading due to file change: {path}", ['path' => $path]);
            }
            $server->reload();
        }
    }

    /**
     * Generates a callable which will call this instance's onTick method with
     * the given swoole server instance. By doing this, we forgo the need to
     * have a swoole server property, and handle the case in which it wouldn't
     * exist.
     */
    private function generateTickCallback(SwooleServer $server) : callable
    {
        $reloader = $this;

        return function () use ($reloader, $server) {
            $reloader->onTick($server);
        };
    }

    /**
     * Start watching included files.
     */
    private function watchIncludedFiles() : void
    {
        foreach (array_diff(get_included_files(), $this->watchedFilePaths) as $filePath) {
            $this->fileWatcher->addFilePath($filePath);
            $this->watchedFilePaths[] = $filePath;
        }
    }
}
