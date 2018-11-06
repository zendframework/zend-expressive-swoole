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
use Swoole\Server;
use Throwable;
use Zend\Expressive\Swoole\Exception\InvalidArgumentException;
use Zend\Expressive\Swoole\ServerFactory;

use const SWOOLE_BASE;
use const SWOOLE_SOCK_TCP;

class ServerFactoryTest extends TestCase
{

    public function testCreateSwooleServerReturnsASwooleHttpServerInstance() : void
    {
        $process = new Process(function (Process $worker) {
            try {
                $swooleServer = new SwooleHttpServer('127.0.0.1', 65535, SWOOLE_BASE, SWOOLE_SOCK_TCP);
                $factory = new ServerFactory($swooleServer);
                $server = $factory->createSwooleServer();
                $this->assertSame($swooleServer, $server);
                $worker->write('Process Complete');
            } catch (Throwable $exception) {
                $worker->write($exception->getMessage());
            }
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('Process Complete', $data);
    }

    public function testSubsequentCallsToCreateSwooleServerReturnSameInstance()
    {
        $process = new Process(function (Process $worker) {
            $swooleServer = new SwooleHttpServer('127.0.0.1', 65534, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $factory = new ServerFactory($swooleServer);
            $server1 = $factory->createSwooleServer();
            $server2 = $factory->createSwooleServer();
            $message = $server2 === $server1 ? 'SAME' : 'NOT SAME';
            $worker->write($message);
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('SAME', $data);
    }

    public function testCreateSwooleServerWillUseProvidedAppendOptionsWhenCreatingInstance() : void
    {
        $options = [
            'daemonize' => false,
            'worker_num' => 1,
        ];
        $process = new Process(function (Process $worker) use ($options) {
            $swooleServer = new SwooleHttpServer('0.0.0.0', 65533, SWOOLE_BASE, SWOOLE_SOCK_TCP);
            $factory = new ServerFactory($swooleServer, $options);
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
            'port' => 65533,
            'options' => $options,
        ], $data);
    }

    public function testAnExceptionIsThrownIfTheServerHasAlreadyStarted() : void
    {
        $process = new Process(function (Process $worker) {
            $swooleServer = new SwooleHttpServer('0.0.0.0', 65533, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
            $swooleServer->on('Request', function() {});
            $swooleServer->on('Start', function (SwooleHttpServer $server) use ($worker) {
                try {
                    new ServerFactory($server);
                    $worker->write('Exception not thrown');
                } catch (Throwable $exception) {
                    $worker->write($exception->getMessage());
                } finally {
                    $server->stop();
                    $server->shutdown();
                }
            });
            $swooleServer->start();
            $worker->exit(0);
        }, true, 1);

        $process->start();
        $data = $process->read();
        Process::wait(true);

        $this->assertSame('The Swoole server has already been started', $data);
    }
}
