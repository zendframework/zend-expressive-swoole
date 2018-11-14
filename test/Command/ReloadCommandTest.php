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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Swoole\Command\ReloadCommand;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;

class ReloadCommandTest extends TestCase
{
    use ReflectMethodTrait;

    public function setUp()
    {
        $this->input  = $this->prophesize(InputInterface::class);
        $this->output = $this->prophesize(OutputInterface::class);
    }

    public function mockApplication()
    {
        $helperSet   = $this->prophesize(HelperSet::class);
        $application = $this->prophesize(Application::class);
        $application
            ->getHelperSet()
            ->will([$helperSet, 'reveal']);
        return $application;
    }

    public function testConstructorAcceptsServerMode()
    {
        $command = new ReloadCommand(SWOOLE_PROCESS);
        $this->assertAttributeSame(SWOOLE_PROCESS, 'serverMode', $command);
        return $command;
    }

    /**
     * @depends testConstructorAcceptsServerMode
     */
    public function testConstructorSetsDefaultName(ReloadCommand $command)
    {
        $this->assertSame('reload', $command->getName());
    }

    /**
     * @depends testConstructorAcceptsServerMode
     */
    public function testReloadCommandIsASymfonyConsoleCommand(ReloadCommand $command)
    {
        $this->assertInstanceOf(Command::class, $command);
    }

    /**
     * @depends testConstructorAcceptsServerMode
     */
    public function testCommandDefinesNumWorkersOption(ReloadCommand $command)
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

    public function testExecuteEndsWithErrorWhenServerModeIsNotProcessMode()
    {
        $command = new ReloadCommand(SWOOLE_BASE);
        $execute = $this->reflectMethod($command, 'execute');

        $this->assertSame(1, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('not configured to run in SWOOLE_PROCESS mode'))
            ->shouldHaveBeenCalled();
    }

    public function testExecuteEndsWithErrorWhenStopCommandFails()
    {
        $command = new ReloadCommand(SWOOLE_PROCESS);

        $stopCommand = $this->prophesize(Command::class);
        $stopCommand
            ->run(
                Argument::that(function ($arg) {
                    TestCase::assertInstanceOf(ArrayInput::class, $arg);
                    TestCase::assertSame('stop', (string) $arg);
                    return $arg;
                }),
                Argument::that([$this->output, 'reveal'])
            )
            ->willReturn(1);

        $application = $this->mockApplication();
        $application->find('stop')->will([$stopCommand, 'reveal']);

        $command->setApplication($application->reveal());

        $execute = $this->reflectMethod($command, 'execute');
        $this->assertSame(1, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('Reloading server'))
            ->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Cannot reload server: unable to stop'))
            ->shouldHaveBeenCalled();
    }

    public function testExecuteEndsWithErrorWhenStartCommandFails()
    {
        $command = new ReloadCommand(SWOOLE_PROCESS);

        $this->input->getOption('num-workers')->willReturn(5);

        $stopCommand = $this->prophesize(Command::class);
        $stopCommand
            ->run(
                Argument::that(function ($arg) {
                    TestCase::assertInstanceOf(ArrayInput::class, $arg);
                    TestCase::assertSame('stop', (string) $arg);
                    return $arg;
                }),
                Argument::that([$this->output, 'reveal'])
            )
            ->willReturn(0);

        $startCommand = $this->prophesize(Command::class);
        $startCommand
            ->run(
                Argument::that(function ($arg) {
                    TestCase::assertInstanceOf(ArrayInput::class, $arg);
                    TestCase::assertSame('start --daemonize=1 --num-workers=5', (string) $arg);
                    return $arg;
                }),
                Argument::that([$this->output, 'reveal'])
            )
            ->willReturn(1);

        $application = $this->mockApplication();
        $application->find('stop')->will([$stopCommand, 'reveal']);
        $application->find('start')->will([$startCommand, 'reveal']);

        $command->setApplication($application->reveal());

        $execute = $this->reflectMethod($command, 'execute');
        $this->assertSame(1, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('Reloading server'))
            ->shouldHaveBeenCalled();

        $this->output
            ->write(Argument::containingString('Waiting for 5 seconds'))
            ->shouldHaveBeenCalled();

        $this->output
            ->write(Argument::containingString('<info>.</info>'))
            ->shouldHaveBeenCalledTimes(5);

        $this->output
            ->writeln(Argument::containingString('[DONE]'))
            ->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Starting server'))
            ->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Cannot reload server: unable to start'))
            ->shouldHaveBeenCalled();
    }

    public function testExecuteEndsWithSuccessWhenBothStopAndStartCommandsSucceed()
    {
        $command = new ReloadCommand(SWOOLE_PROCESS);

        $this->input->getOption('num-workers')->willReturn(5);

        $stopCommand = $this->prophesize(Command::class);
        $stopCommand
            ->run(
                Argument::that(function ($arg) {
                    TestCase::assertInstanceOf(ArrayInput::class, $arg);
                    TestCase::assertSame('stop', (string) $arg);
                    return $arg;
                }),
                Argument::that([$this->output, 'reveal'])
            )
            ->willReturn(0);

        $startCommand = $this->prophesize(Command::class);
        $startCommand
            ->run(
                Argument::that(function ($arg) {
                    TestCase::assertInstanceOf(ArrayInput::class, $arg);
                    TestCase::assertSame('start --daemonize=1 --num-workers=5', (string) $arg);
                    return $arg;
                }),
                Argument::that([$this->output, 'reveal'])
            )
            ->willReturn(0);

        $application = $this->mockApplication();
        $application->find('stop')->will([$stopCommand, 'reveal']);
        $application->find('start')->will([$startCommand, 'reveal']);

        $command->setApplication($application->reveal());

        $execute = $this->reflectMethod($command, 'execute');
        $this->assertSame(0, $execute->invoke(
            $command,
            $this->input->reveal(),
            $this->output->reveal()
        ));

        $this->output
            ->writeln(Argument::containingString('Reloading server'))
            ->shouldHaveBeenCalled();

        $this->output
            ->write(Argument::containingString('Waiting for 5 seconds'))
            ->shouldHaveBeenCalled();

        $this->output
            ->write(Argument::containingString('<info>.</info>'))
            ->shouldHaveBeenCalledTimes(5);

        $this->output
            ->writeln(Argument::containingString('[DONE]'))
            ->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Starting server'))
            ->shouldHaveBeenCalled();

        $this->output
            ->writeln(Argument::containingString('Cannot reload server'))
            ->shouldNotHaveBeenCalled();
    }
}
