<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Throwable;
use Zend\Expressive\Swoole\ServerFactory;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const SWOOLE_SOCK_TCP;
use const SWOOLE_SOCK_TCP6;
use const SWOOLE_SOCK_UDP;
use const SWOOLE_SOCK_UDP6;
use const SWOOLE_SSL;
use const SWOOLE_UNIX_DGRAM;
use const SWOOLE_UNIX_STREAM;

class ServerFactoryTest extends TestCase
{
    public function testCreateSwooleServerCreatesAndReturnsASwooleHttpServerInstance()
    {
        $process = new Process(function ($worker) {
            $factory = new ServerFactory('0.0.0.0', 65535, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $server = $factory->createSwooleServer();
            $worker->write(sprintf('%s:%d', $server->host, $server->port));
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('0.0.0.0:65535', $data);
    }

    public function testSubsequentCallsToCreateSwooleServerReturnSameInstance()
    {
        $process = new Process(function ($worker) {
            $factory = new ServerFactory('0.0.0.0', 65535, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $server = $factory->createSwooleServer();
            $server2 = $factory->createSwooleServer();
            $message = $server2 === $server ? 'SAME' : 'NOT SAME';
            $worker->write($message);
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('SAME', $data);
    }

    public function testCreateSwooleServerWillUseProvidedAppendOptionsWhenCreatingInstance()
    {
        $options = [
            'daemonize' => false,
            'worker_num' => 1,
        ];
        $process = new Process(function ($worker) use ($options) {
            $factory = new ServerFactory('0.0.0.0', 65535, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $server = $factory->createSwooleServer($options);
            $worker->write(serialize([
                'host' => $server->host,
                'port' => $server->port,
                'options' => $server->setting,
            ]));
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = unserialize($process->read());
        Process::wait(true);

        $this->assertSame([
            'host' => '0.0.0.0',
            'port' => 65535,
            'options' => $options,
        ], $data);
    }

    public function validSocketTypes() : iterable
    {
        yield 'SWOOLE_SOCK_TCP'    => [SWOOLE_SOCK_TCP, []];
        yield 'SWOOLE_SOCK_TCP6'   => [SWOOLE_SOCK_TCP6, []];
        yield 'SWOOLE_SOCK_UDP'    => [SWOOLE_SOCK_UDP, []];
        yield 'SWOOLE_SOCK_UDP6'   => [SWOOLE_SOCK_UDP6, []];
        yield 'SWOOLE_UNIX_DGRAM'  => [SWOOLE_UNIX_DGRAM, []];
        yield 'SWOOLE_UNIX_STREAM' => [SWOOLE_UNIX_STREAM, []];

        if (defined('SWOOLE_SSL')) {
            $extraOptions = [
                'ssl_cert_file' => __DIR__ . '/TestAsset/ssl/server.crt',
                'ssl_key_file' => __DIR__ . '/TestAsset/ssl/server.key',
            ];
            yield 'SWOOLE_SOCK_TCP | SWOOLE_SSL'  => [SWOOLE_SOCK_TCP | SWOOLE_SSL, $extraOptions];
            yield 'SWOOLE_SOCK_TCP6 | SWOOLE_SSL' => [SWOOLE_SOCK_TCP6 | SWOOLE_SSL, $extraOptions];
        }
    }

    /**
     * @dataProvider validSocketTypes
     */
    public function testServerCanBeStartedForKnownSocketTypeCombinations($socketType, array $additionalOptions)
    {
        $process = new Process(function (Process $worker) use ($socketType, $additionalOptions) {
            try {
                $factory = new ServerFactory('127.0.0.1', 65535, SWOOLE_PROCESS, $socketType, $additionalOptions);
                $swooleServer = $factory->createSwooleServer();
                $swooleServer->on('Start', function (SwooleHttpServer $server) use ($worker) {
                    // Give the server a chance to start up and avoid zombies
                    usleep(10000);
                    $worker->write('Server Started');
                    $server->stop();
                    $server->shutdown();
                });
                $swooleServer->on('Request', function ($req, $rep) {
                    // noop
                });
                $swooleServer->start();
            } catch (Throwable $exception) {
                $worker->write('Exception Thrown: ' . $exception->getMessage());
            }
            $worker->exit();
        });

        $process->start();
        $output = $process->read();
        Process::wait(true);
        $this->assertSame('Server Started', $output);
    }
}
