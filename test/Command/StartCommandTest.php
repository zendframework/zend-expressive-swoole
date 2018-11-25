<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Command;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ProphecyInterface;
use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Swoole\Command\StartCommand;
use Zend\Expressive\Swoole\PidManager;

use function getmypid;
use function get_include_path;
use function realpath;
use function set_include_path;
use function sprintf;

use const PATH_SEPARATOR;

class StartCommandTest extends TestCase
{
    use ReflectMethodTrait;

    public function setUp()
    {
        $this->container  = $this->prophesize(ContainerInterface::class);
        $this->input      = $this->prophesize(InputInterface::class);
        $this->output     = $this->prophesize(OutputInterface::class);
        $this->pidManager = $this->prophesize(PidManager::class);

        $this->originalIncludePath = get_include_path();
        set_include_path(sprintf(
            '%s/TestAsset%s%s',
            realpath(__DIR__),
            PATH_SEPARATOR,
            $this->originalIncludePath
        ));
    }

    public function tearDown()
    {
        set_include_path($this->originalIncludePath);
    }

    public function pushServiceToContainer(string $name, $instance)
    {
        if ($instance instanceof ProphecyInterface) {
            $instance = $instance->reveal();
        }
        $this->container->get($name)->willReturn($instance);
    }

    public function testConstructorAcceptsContainer()
    {
        $command = new StartCommand($this->container->reveal());
        $this->assertAttributeSame($this->container->reveal(), 'container', $command);
        return $command;
    }

    /**
     * @depends testConstructorAcceptsContainer
     */
    public function testConstructorSetsDefaultName(StartCommand $command)
    {
        $this->assertSame('start', $command->getName());
    }

    /**
     * @depends testConstructorAcceptsContainer
     */
    public function testStartCommandIsASymfonyConsoleCommand(StartCommand $command)
    {
        $this->assertInstanceOf(Command::class, $command);
    }

    /**
     * @depends testConstructorAcceptsContainer
     */
    public function testCommandDefinesNumWorkersOption(StartCommand $command)
    {
        $this->assertTrue($command->getDefinition()->hasOption('num-workers'));
        return $command->getDefinition()->getOption('num-workers');
    }

    /**
     * @depends testCommandDefinesNumWorkersOption
     */
    public function testNumWorkersOptionIsRequired(InputOption $option)
    {
        $this->assertTrue($option->isValueRequired());
    }

    /**
     * @depends testCommandDefinesNumWorkersOption
     */
    public function testNumWorkersOptionDefinesShortOption(InputOption $option)
    {
        $this->assertSame('w', $option->getShortcut());
    }

    /**
     * @depends testConstructorAcceptsContainer
     */
    public function testCommandDefinesDaemonizeOption(StartCommand $command)
    {
        $this->assertTrue($command->getDefinition()->hasOption('daemonize'));
        return $command->getDefinition()->getOption('daemonize');
    }

    /**
     * @depends testCommandDefinesDaemonizeOption
     */
    public function testDaemonizeOptionHasNoValue(InputOption $option)
    {
        $this->assertFalse($option->acceptValue());
    }

    /**
     * @depends testCommandDefinesDaemonizeOption
     */
    public function testDaemonizeOptionDefinesShortOption(InputOption $option)
    {
        $this->assertSame('d', $option->getShortcut());
    }

    public function testExecuteReturnsErrorIfServerIsRunningInBaseMode()
    {
        $this->pidManager->read()->willReturn([getmypid(), null]);
        $this->pushServiceToContainer(PidManager::class, $this->pidManager);

        $command = new StartCommand($this->container->reveal());

        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(1, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('Server is already running'))
            ->shouldHaveBeenCalled();
    }

    public function testExecuteReturnsErrorIfServerIsRunningInProcessMode()
    {
        $this->pidManager->read()->willReturn([1000000, getmypid()]);
        $this->pushServiceToContainer(PidManager::class, $this->pidManager);

        $command = new StartCommand($this->container->reveal());

        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(1, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('Server is already running'))
            ->shouldHaveBeenCalled();
    }

    public function noRunningProcesses() : iterable
    {
        yield 'empty'        => [[]];
        yield 'null-all'     => [[null, null]];
        yield 'base-mode'    => [[1000000, null]];
        yield 'process-mode' => [[1000000, 1000001]];
    }

    /**
     * @dataProvider noRunningProcesses
     */
    public function testExecuteRunsApplicationIfServerIsNotCurrentlyRunning(array $pids)
    {
        $this->input->getOption('daemonize')->willReturn(true);
        $this->input->getOption('num-workers')->willReturn(6);

        $this->pidManager->read()->willReturn($pids);
        $this->pushServiceToContainer(PidManager::class, $this->pidManager);

        $httpServer = $this->prophesize(TestAsset\HttpServer::class);
        $this->pushServiceToContainer(SwooleHttpServer::class, $httpServer);

        $middlewareFactory = $this->prophesize(MiddlewareFactory::class);
        $this->pushServiceToContainer(MiddlewareFactory::class, $middlewareFactory);

        $application = $this->prophesize(Application::class);
        $this->pushServiceToContainer(Application::class, $application);

        $command = new StartCommand($this->container->reveal());

        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(0, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $httpServer
            ->set(Argument::that(function ($options) {
                TestCase::assertArrayHasKey('daemonize', $options);
                TestCase::assertArrayHasKey('worker_num', $options);
                TestCase::assertTrue($options['daemonize']);
                TestCase::assertSame(6, $options['worker_num']);
                return $options;
            }))
            ->shouldHaveBeenCalled();

        $application->run()->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Server is already running'))
            ->shouldNotHaveBeenCalled();
    }

    /**
     * @dataProvider noRunningProcesses
     */
    public function testExecuteRunsApplicationWithoutSettingOptionsIfNoneProvided(array $pids)
    {
        $this->input->getOption('daemonize')->willReturn(false);
        $this->input->getOption('num-workers')->willReturn(null);

        $this->pidManager->read()->willReturn($pids);
        $this->pushServiceToContainer(PidManager::class, $this->pidManager);

        $httpServer = $this->prophesize(TestAsset\HttpServer::class);
        $this->pushServiceToContainer(SwooleHttpServer::class, $httpServer);

        $middlewareFactory = $this->prophesize(MiddlewareFactory::class);
        $this->pushServiceToContainer(MiddlewareFactory::class, $middlewareFactory);

        $application = $this->prophesize(Application::class);
        $this->pushServiceToContainer(Application::class, $application);

        $command = new StartCommand($this->container->reveal());

        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(0, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->container->get(SwooleHttpServer::class)->shouldNotHaveBeenCalled();

        $httpServer
            ->set(Argument::any())
            ->shouldNotHaveBeenCalled();

        $application->run()->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Server is already running'))
            ->shouldNotHaveBeenCalled();
    }

    public function testExecutionDoesNotFailEvenIfProgrammaticConfigFilesDoNotExist()
    {
        set_include_path($this->originalIncludePath);

        $this->pidManager->read()->willReturn([]);
        $this->pushServiceToContainer(PidManager::class, $this->pidManager);

        $httpServer = $this->prophesize(TestAsset\HttpServer::class);
        $this->pushServiceToContainer(SwooleHttpServer::class, $httpServer);

        $middlewareFactory = $this->prophesize(MiddlewareFactory::class);
        $this->pushServiceToContainer(MiddlewareFactory::class, $middlewareFactory);

        $application = $this->prophesize(Application::class);
        $this->pushServiceToContainer(Application::class, $application);

        $command = new StartCommand($this->container->reveal());

        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(0, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));
    }
}
