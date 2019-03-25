<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleServer;
use Swoole\Process;
use Swoole\Runtime as SwooleRuntime;
use Throwable;
use Zend\Expressive\Swoole\Exception\InvalidArgumentException;
use Zend\Expressive\Swoole\HttpServerFactory;

use function array_merge;
use function defined;
use function json_decode;
use function json_encode;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const SWOOLE_SOCK_TCP;
use const SWOOLE_SOCK_TCP6;
use const SWOOLE_SOCK_UDP;
use const SWOOLE_SOCK_UDP6;
use const SWOOLE_SSL;
use const SWOOLE_UNIX_DGRAM;
use const SWOOLE_UNIX_STREAM;

class HttpServerFactoryTest extends TestCase
{

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    public function setUp()
    {
        parent::setUp();
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testFactoryCanCreateServerWithDefaultConfiguration() : void
    {
        /**
         * Initialise servers inside a process or subsequent tests will fail
         * @see https://github.com/swoole/swoole-src/issues/1754
         */
        $process = new Process(function (Process $worker) {
            $this->container->get('config')->willReturn([]);
            $factory = new HttpServerFactory();
            $swooleServer = $factory($this->container->reveal());
            $this->assertSame(HttpServerFactory::DEFAULT_HOST, $swooleServer->host);
            $this->assertSame(HttpServerFactory::DEFAULT_PORT, $swooleServer->port);
            $this->assertSame(SWOOLE_BASE, $swooleServer->mode);
            $this->assertSame(SWOOLE_SOCK_TCP, $swooleServer->type);
            $worker->write('Process Complete');
            $worker->exit(0);
        });
        $process->start();
        $this->assertSame('Process Complete', $process->read());
        Process::wait(true);
    }

    public function testFactorySetsPortAndHostAsConfigured() : void
    {
        $process = new Process(function (Process $worker) {
            $this->container->get('config')->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'host' => '0.0.0.0',
                        'port' => 8081,
                        'mode' => SWOOLE_BASE,
                        'protocol' => SWOOLE_SOCK_TCP6,
                    ],
                ],
            ]);
            $factory = new HttpServerFactory();
            $swooleServer = $factory($this->container->reveal());
            $worker->write(json_encode([
                'host' => $swooleServer->host,
                'port' => $swooleServer->port,
                'mode' => $swooleServer->mode,
                'type' => $swooleServer->type,
            ]));
            $worker->exit(0);
        });
        $process->start();
        $data = $process->read();
        Process::wait(true);

        $result = json_decode($data, true);
        $this->assertSame([
            'host' => '0.0.0.0',
            'port' => 8081,
            'mode' => SWOOLE_BASE,
            'type' => SWOOLE_SOCK_TCP6,
        ], $result);
    }

    public function getInvalidPortNumbers() : array
    {
        return [
            [-1],
            [0],
            [65536],
            [999999],
        ];
    }

    /**
     * @dataProvider getInvalidPortNumbers
     * @param int $port
     */
    public function testExceptionThrownForOutOfRangePortNumber(int $port) : void
    {
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'port' => $port,
                ],
            ],
        ]);
        $factory = new HttpServerFactory();
        try {
            $factory($this->container->reveal());
            $this->fail('An exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid port', $e->getMessage());
        }
    }

    public function invalidServerModes() : array
    {
        return [
            [0],
            [(string) SWOOLE_BASE],
            [(string) SWOOLE_PROCESS],
            [10],
        ];
    }

    /**
     * @dataProvider invalidServerModes
     * @param mixed $mode
     */
    public function testExceptionThrownForInvalidServerMode($mode) : void
    {
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'mode' => $mode,
                ],
            ],
        ]);
        $factory = new HttpServerFactory();
        try {
            $factory($this->container->reveal());
            $this->fail('An exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid server mode', $e->getMessage());
        }
    }

    public function invalidSocketTypes() : array
    {
        return [
            [0],
            [(string) SWOOLE_SOCK_TCP],
            [(string) SWOOLE_SOCK_TCP6],
            [10],
        ];
    }

    /**
     * @dataProvider invalidSocketTypes
     * @param mixed $type
     */
    public function testExceptionThrownForInvalidSocketType($type) : void
    {
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'protocol' => $type,
                ],
            ],
        ]);
        $factory = new HttpServerFactory();
        try {
            $factory($this->container->reveal());
            $this->fail('An exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid server protocol', $e->getMessage());
        }
    }

    public function testServerOptionsAreCorrectlySetFromConfig()
    {
        $serverOptions = [
            'pid_file' => '/tmp/swoole.pid',
        ];
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'options' => $serverOptions,
                ],
            ],
        ]);
        $process = new Process(function (Process $worker) {
            $factory = new HttpServerFactory();
            $swooleServer = $factory($this->container->reveal());
            $worker->write(json_encode($swooleServer->setting));
            $worker->exit();
        });
        $process->start();
        $setOptions = json_decode($process->read(), true);
        Process::wait(true);
        $this->assertSame($serverOptions, $setOptions);
    }

    public function validSocketTypes() : array
    {
        $validTypes = [
            [SWOOLE_SOCK_TCP, []],
            [SWOOLE_SOCK_TCP6, []],
            [SWOOLE_SOCK_UDP, []],
            [SWOOLE_SOCK_UDP6, []],
            [SWOOLE_UNIX_DGRAM, []],
            [SWOOLE_UNIX_STREAM, []],
        ];

        if (defined('SWOOLE_SSL')) {
            $extraOptions = [
                'ssl_cert_file' => __DIR__ . '/TestAsset/ssl/server.crt',
                'ssl_key_file' => __DIR__ . '/TestAsset/ssl/server.key',
            ];
            $validTypes[] = [SWOOLE_SOCK_TCP | SWOOLE_SSL, $extraOptions];
            $validTypes[] = [SWOOLE_SOCK_TCP6 | SWOOLE_SSL, $extraOptions];
        }

        return $validTypes;
    }

    /**
     * @dataProvider validSocketTypes
     * @param int $socketType
     * @param array $additionalOptions
     */
    public function testServerCanBeStartedForKnownSocketTypeCombinations($socketType, array $additionalOptions) : void
    {
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'protocol' => $socketType,
                    'mode' => SWOOLE_PROCESS,
                    'options' => array_merge([
                        'worker_num' => 1,
                    ], $additionalOptions),
                ],
            ],
        ]);
        $process = new Process(function (Process $worker) {
            try {
                $factory = new HttpServerFactory();
                $swooleServer = $factory($this->container->reveal());
                $swooleServer->on('Start', function (SwooleServer $server) use ($worker) {
                    // Give the server a chance to start up and avoid zombies
                    usleep(10000);
                    $worker->write('Server Started');
                    $server->stop();
                    $server->shutdown();
                });
                $swooleServer->on('Request', function ($req, $rep) {
                    // noop
                });
                $swooleServer->on('Packet', function ($server, $data, $clientInfo) {
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

    public function testFactoryCanBeEnableCoroutine()
    {
        $this->container->get('config')->willReturn([
            'zend-expressive-swoole' => [
                'enable_coroutine' => true,
            ],
        ]);
        $factory = new HttpServerFactory();
        $factory($this->container->reveal());

        $this->assertFalse(SwooleRuntime::enableCoroutine());
    }
}
