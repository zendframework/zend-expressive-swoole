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
    protected $logger;

    protected $pidFile = '';

    /**
     * PidManager constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct($pidFile, LoggerInterface $logger = null)
    {
        $this->pidFile = $pidFile;
        $this->logger = $logger ? : new StdoutLogger();
    }


    /**
     * Write master pid and manager pid to pid file
     */
    public function write(int $masterPid, int $managerPid)
    {
        file_put_contents($this->getPidFile(), $masterPid . ',' . $managerPid);
    }

    /**
     * Read master pid and manager pid from pid file
     */
    public function read(): array
    {
        $pids = [];
        if (file_exists($this->getPidFile())) {
            $content = file_get_contents($this->getPidFile());
            $pids = explode(',', $content);
        }
        return $pids;
    }

    /**
     * Delete pid file
     */
    public function delete(): bool
    {
        if ($this->exist()) {
            return unlink($this->getPidFile());
        }
        return false;
    }

    /**
     * Is pid file exist ?
     */
    public function exist(): bool
    {
        return file_exists($this->getPidFile());
    }

    /**
     * @return string
     */
    public function getPidFile(): string
    {
        return $this->pidFile;
    }

    /**
     * @param string $pidFile
     * @return PidManager
     */
    public function setPidFile($pidFile)
    {
        $this->pidFile = $pidFile;
        return $this;
    }
}
