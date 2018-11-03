<?php
declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleHttpServer;

use function in_array;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const SWOOLE_SOCK_TCP;
use const SWOOLE_SOCK_TCP6;
use const SWOOLE_SOCK_UDP;
use const SWOOLE_SOCK_UDP6;
use const SWOOLE_UNIX_DGRAM;
use const SWOOLE_UNIX_STREAM;

class HttpServerFactory
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 8080;

    /**
     * Swoole server supported modes
     */
    public const MODES = [
        SWOOLE_BASE,
        SWOOLE_PROCESS
    ];

    /**
     * Swoole server supported protocols
     */
    public const PROTOCOLS = [
        SWOOLE_SOCK_TCP,
        SWOOLE_SOCK_TCP6,
        SWOOLE_SOCK_UDP,
        SWOOLE_SOCK_UDP6,
        SWOOLE_UNIX_DGRAM,
        SWOOLE_UNIX_STREAM
    ];

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-server-methods#swoole_server-__construct
     * @see https://www.swoole.co.uk/docs/modules/swoole-server/predefined-constants for $mode and $protocol constant
     * @throws Exception\InvalidArgumentException for invalid $port values
     * @throws Exception\InvalidArgumentException for invalid $mode values
     * @throws Exception\InvalidArgumentException for invalid $protocol values
     */
    public function __invoke(ContainerInterface $container) : SwooleHttpServer
    {
        $config = $container->get('config');
        $swooleConfig = $config['zend-expressive-swoole']['swoole-http-server'] ?? [];

        $host = $swooleConfig['host'] ?? static::DEFAULT_HOST;
        $port = $swooleConfig['port'] ?? static::DEFAULT_PORT;
        $mode = $swooleConfig['mode'] ?? SWOOLE_BASE;
        $protocol = $swooleConfig['protocol'] ?? SWOOLE_SOCK_TCP;

        if ($port < 1 || $port > 65535) {
            throw new Exception\InvalidArgumentException('Invalid port');
        }

        if (! in_array($mode, static::MODES, true)) {
            throw new Exception\InvalidArgumentException('Invalid server mode');
        }

        if (! in_array($protocol, static::PROTOCOLS, true)) {
            throw new Exception\InvalidArgumentException('Invalid server protocol');
        }

        return new SwooleHttpServer($host, $port, $mode, $protocol);
    }
}