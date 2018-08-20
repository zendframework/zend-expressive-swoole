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
use Zend\Expressive\Swoole\ServerFactory;

use const SWOOLE_BASE;
use const SWOOLE_SOCK_TCP;

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
}
