<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Log\LoggerInterface;
use Zend\Expressive\Swoole\Exception\RuntimeException;

use function file_put_contents;
use function file_get_contents;
use function sprintf;
use function explode;
use function is_readable;
use function is_writable;
use function unlink;

class PidManager
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $pidFile = '';

    public function __construct(string $pidFile, LoggerInterface $logger = null)
    {
        $this->pidFile = $pidFile;
        $this->logger = $logger ?: new StdoutLogger();
    }


    /**
     * Write master pid and manager pid to pid file
     *
     * @throws \RuntimeException When $pidFile is not writable
     */
    public function write(int $masterPid, int $managerPid) : void
    {
        if (! is_writable($this->pidFile)) {
            throw new RuntimeException(sprintf('Pid file %s is not writable', $this->pidFile));
        }
        file_put_contents($this->pidFile, $masterPid . ',' . $managerPid);
    }

    /**
     * Read master pid and manager pid from pid file
     *
     * @return array [masterPid, managerPid]
     */
    public function read() : array
    {
        $pids = [];
        if (is_readable($this->pidFile)) {
            $content = file_get_contents($this->pidFile);
            $pids = explode(',', $content);
        }
        return $pids;
    }

    /**
     * Delete pid file
     */
    public function delete() : bool
    {
        if (is_writable($this->pidFile)) {
            return unlink($this->pidFile);
        }
        return false;
    }
}
