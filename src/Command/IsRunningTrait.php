<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Swoole\Process as SwooleProcess;
use Zend\Expressive\Swoole\PidManager;

trait IsRunningTrait
{
    /**
     * Is the swoole HTTP server running?
     */
    public function isRunning() : bool
    {
        $pids = $this->pidManager->read();

        if ([] === $pids) {
            return false;
        }

        [$masterPid, $managerPid] = $pids;

        if ($managerPid) {
            // Swoole process mode
            return $masterPid && $managerPid && SwooleProcess::kill((int) $managerPid, 0);
        }

        // Swoole base mode, no manager process
        return $masterPid && SwooleProcess::kill((int) $masterPid, 0);
    }
}
