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
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var int
     */
    protected $mode;

    /**
     * @var int
     */
    protected $protocol;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var SwooleHttpServer
     */
    protected $swooleServer;

    public function __construct(string $host, int $port, int $mode, int $protocol, array $options)
    {
        $this->host = $host;
        $this->port = $port;
        $this->mode = $mode;
        $this->protocol = $protocol;
        $this->options = $options;
    }

    /**
     * Create a swoole server instance
     */
    public function createSwooleServer(array $appendOptions = []): SwooleHttpServer
    {
        if (! $this->swooleServer) {
            $server = new SwooleHttpServer($this->host, $this->port, $this->mode, $this->protocol);
            if ($options = array_replace($this->options, $appendOptions)) {
                $server->set($options);
            }
            $this->setSwooleServer($server);
        }
        return $this->getSwooleServer();
    }

    public function getSwooleServer(): SwooleHttpServer
    {
        return $this->swooleServer;
    }

    public function setSwooleServer(SwooleHttpServer $swooleServer)
    {
        $this->swooleServer = $swooleServer;
        return $this;
    }
}
