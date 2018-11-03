<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Runtime as SwooleRuntime;

use function array_replace;
use function method_exists;

class ServerFactory
{

    /**
     * Enable coroutines within the Swoole HTTP server.
     *
     * ONLY available for swoole 4.1.0 or later version.
     *
     * When running in coroutine mode, PDO/Mysqli (when Swoole is compiled with
     * --enable-mysqlnd), Redis, SOAP, file_get_contents, fopen(ONLY TCP, FTP,
     * HTTP protocol), stream_socket_client, and the fsockopen functions will
     * automatically switch to a non-blocking, async I/O driver. Avoid blocking
     * I/O when enabling coroutines.
     *
     * @var bool
     */
    private $enableCoroutine = false;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var SwooleHttpServer
     */
    private $swooleServer;

    public function __construct(SwooleHttpServer $swooleServer, array $options = [])
    {
        /**
         * It's expected that the swoole server will not yet be running, so an exception is thrown
         * if the master or manager pids are > 0
         */
        if ($swooleServer->master_pid > 0 || $swooleServer->manager_pid > 0) {
            throw new Exception\InvalidArgumentException('The Swoole server has already been started');
        }
        $this->swooleServer = $swooleServer;
        $this->options = $options;

        // If provided, and Swoole 4.1.0 or later is in use, this flag can be
        // used to enable coroutines for most I/O operations.
        $this->enableCoroutine = $options['enable_coroutine'] ?? false;
    }

    /**
     * Create a swoole server instance
     *
     * @see https://www.swoole.co.uk/docs/modules/swoole-server-methods#swoole_server-set for server options
     */
    public function createSwooleServer(array $appendOptions = []): SwooleHttpServer
    {
        if ($this->enableCoroutine && method_exists(SwooleRuntime::class, 'enableCoroutine')) {
            SwooleRuntime::enableCoroutine(true);
        }

        $options = array_replace($this->options, $appendOptions);
        if ([] !== $options) {
            $this->swooleServer->set($options);
        }

        return $this->swooleServer;
    }
}
