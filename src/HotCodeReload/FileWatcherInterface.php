<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\HotCodeReload;

interface FileWatcherInterface
{
    /**
     * Add a file path to be monitored for changes by this watcher.
     *
     * @param string $path
     */
    public function addFilePath(string $path) : void;

    /**
     * Returns file paths for files that changed since last read.
     *
     * @return string[]
     */
    public function readChangedFilePaths() : array;
}
