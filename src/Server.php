<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Swoole\Http\Server as SwooleHttpServer;

class Server
{

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var int
     */
    private $mode;

    /**
     * @var int
     */
    private $protocol;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var SwooleHttpServer
     */
    private $swooleServer;

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-server-methods#swoole_server-__construct
     * @see https://www.swoole.co.uk/docs/modules/swoole-server/predefined-constants for $mode and $protocol constant
     */
    public function __construct(string $host, int $port, int $mode, int $protocol, array $options = [])
    {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Invalid port');
        }
        if (! \in_array($mode, [SWOOLE_BASE, SWOOLE_PROCESS ], true)) {
            throw new \InvalidArgumentException('Invalid server mode');
        }
        if (! \in_array($protocol, [
            SWOOLE_SOCK_TCP,
            SWOOLE_SOCK_TCP6,
            SWOOLE_SOCK_UDP,
            SWOOLE_SOCK_UDP6,
            SWOOLE_UNIX_DGRAM,
            SWOOLE_UNIX_STREAM
        ], true)) {
            throw new \InvalidArgumentException('Invalid server protocol');
        }
        $this->host = $host;
        $this->port = $port;
        $this->mode = $mode;
        $this->protocol = $protocol;
        $this->options = $options;
    }

    /**
     * Create a swoole server instance
     *
     * @see https://www.swoole.co.uk/docs/modules/swoole-server-methods#swoole_server-set for server options
     */
    public function createSwooleServer(array $appendOptions = []): SwooleHttpServer
    {
        if ($this->swooleServer) {
            return $this->swooleServer;
        }
        $this->swooleServer = new SwooleHttpServer($this->host, $this->port, $this->mode, $this->protocol);
        $options = array_replace($this->options, $appendOptions);
        if ([] !== $options) {
            $this->swooleServer->set($options);
        }
        return $this->swooleServer;
    }

    public function setSwooleServer(SwooleHttpServer $swooleServer) : self
    {
        $this->swooleServer = $swooleServer;
        return $this;
    }
}
