<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use function file_put_contents;
use Psr\Log\LoggerInterface;

class PidManager
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $pidFile = '';

    /**
     * PidManager constructor.
     */
    public function __construct(string $pidFile, LoggerInterface $logger = null)
    {
        $this->pidFile = $pidFile;
        $this->logger = $logger ? : new StdoutLogger();
    }


    /**
     * Write master pid and manager pid to pid file
     *
     * @throws \RuntimeException When $pidFile is not writable
     */
    public function write(int $masterPid, int $managerPid) : void
    {
        $pidFile = $this->getPidFile();
        if (! is_writable($pidFile)) {
            throw new \RuntimeException(sprintf('Pid file %s is not writable', $pidFile));
        }
        file_put_contents($pidFile, $masterPid . ',' . $managerPid);
    }

    /**
     * Read master pid and manager pid from pid file
     */
    public function read() : array
    {
        $pids = [];
        $pidFile = $this->getPidFile();
        if (is_readable($pidFile)) {
            $content = file_get_contents($pidFile);
            $pids = explode(',', $content);
        }
        return $pids;
    }

    /**
     * Delete pid file
     */
    public function delete() : bool
    {
        $pidFile = $this->getPidFile();
        if (is_writable($pidFile)) {
            return unlink($pidFile);
        }
        return false;
    }

    public function getPidFile() : string
    {
        return $this->pidFile;
    }

    public function setPidFile(string $pidFile) : self
    {
        $this->pidFile = $pidFile;
        return $this;
    }
}
