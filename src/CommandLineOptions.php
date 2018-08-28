<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

class CommandLineOptions
{
    public const ACTION_HELP = 'help';
    public const ACTION_START = 'start';
    public const ACTION_STOP = 'stop';
    public const ACTION_RELOAD = 'reload';

    /** @var string */
    private $action;

    /** @var bool */
    private $daemonize;

    /** @var int */
    private $numWorkers;

    public function __construct(string $action, bool $daemonize, int $numWorkers)
    {
        $this->action = $action;
        $this->daemonize = $daemonize;
        $this->numWorkers = $numWorkers;
    }

    public function getAction() : string
    {
        return $this->action;
    }

    public function daemonize() : bool
    {
        return $this->daemonize;
    }

    public function getNumberOfWorkers() : int
    {
        return $this->numWorkers;
    }
}
