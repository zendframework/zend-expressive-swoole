<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Swoole\CommandLine;
use Zend\Expressive\Swoole\CommandLineOptions;

class CommandLineTest extends TestCase
{
    public function mockExit(CommandLine $commandLine, int $expectedStatus, string $message = null) : void
    {
        $commandLine->setExitFunction(function (int $status) use ($expectedStatus, $message) {
            $message = $message ?: sprintf('Expected exit status of %d, but received %d', $expectedStatus, $status);
            TestCase::assertSame($expectedStatus, $status, $message);
        });
    }

    public function assertStreamContains(string $expected, $stream, string $message = null) : void
    {
        $message = $message ?: sprintf('Failed asserting stream contains "%s"', $expected);
        rewind($stream);
        $contents = stream_get_contents($stream);
        $regex = sprintf('/%s/', preg_quote($expected, '/'));
        TestCase::assertRegExp($regex, $contents, $message);
    }

    public function testConstructorDiscoversCommandAndArgumentsFromProvidedArray()
    {
        $commandLine = new CommandLine(['test.php', '--daemonize', '--num_workers', '5']);
        $this->assertAttributeSame('test.php', 'commandName', $commandLine);
        $this->assertAttributeSame(['--daemonize', '--num_workers', '5'], 'arguments', $commandLine);
    }

    public function unrecognizedArguments() : iterable
    {
        return [
            'single argument' => [['unknown']],
            'multiple arguments, invalid first' => [['unknown', 'start']],
            'multiple arguments, valid first' => [['start', 'unknown']],
        ];
    }

    /**
     * @dataProvider unrecognizedArguments
     */
    public function testParseExitsWithErrorForUnrecognizedArguments(array $args)
    {
        array_unshift($args, 'command');
        $commandLine = new CommandLine($args);

        $stderrStream = fopen('php://memory', 'wb+');
        $commandLine->setStderrStream($stderrStream);
        $this->mockExit($commandLine, 1);

        $this->assertNull($commandLine->parse());
        $this->assertStreamContains('An unexpected argument was encountered', $stderrStream);
    }

    public function unrecognizedOptions() : iterable
    {
        return [
            'unknown short flag' => [['-u']],
            'unknown long flag' => [['--unknown']],
            'unknown short flag preceding known argument' => [['-t', 'start']],
            'unknown short flag following known argument' => [['start', '-t']],
            'unknown long flag preceding known argument' => [['--test', 'start']],
            'unknown long flag following known argument' => [['start', '--test']],
        ];
    }

    /**
     * @dataProvider unrecognizedOptions
     */
    public function testParseExitsWithErrorForUnrecognizedOptions(array $args)
    {
        array_unshift($args, 'command');
        $commandLine = new CommandLine($args);

        $stderrStream = fopen('php://memory', 'wb+');
        $commandLine->setStderrStream($stderrStream);
        $this->mockExit($commandLine, 1);

        $this->assertNull($commandLine->parse());
        $this->assertStreamContains('An unexpected option was encountered', $stderrStream);
    }

    public function testParseExitsWithErrorWhenOptionHasUnexpectedValue()
    {
        $args = ['command', 'start', '-n', 'string'];
        $commandLine = new CommandLine($args);

        $stderrStream = fopen('php://memory', 'wb+');
        $commandLine->setStderrStream($stderrStream);
        $this->mockExit($commandLine, 1);

        $this->assertNull($commandLine->parse());
        $this->assertStreamContains(
            'An unexpected value for the option "num_workers" was encountered',
            $stderrStream
        );
    }

    public function validCommandLines() : iterable
    {
        // @codingStandardsIgnoreStart
        return [
            // 'name' => [$argv, $action, $daemonize, $numWorkers]
            'empty' => [[], CommandLineOptions::ACTION_START, false, 1],
            // actions only
            'help' => [[CommandLineOptions::ACTION_HELP], CommandLineOptions::ACTION_HELP, false, 1],
            'start' => [[CommandLineOptions::ACTION_START], CommandLineOptions::ACTION_START, false, 1],
            'stop' => [[CommandLineOptions::ACTION_STOP], CommandLineOptions::ACTION_STOP, false, 1],
            // --help
            'short-help start leading' => [['-h', CommandLineOptions::ACTION_START], CommandLineOptions::ACTION_HELP, false, 1],
            'short-help start trailing' => [[CommandLineOptions::ACTION_START, '-h'], CommandLineOptions::ACTION_HELP, false, 1],
            'long-help start leading' => [['--help', CommandLineOptions::ACTION_START], CommandLineOptions::ACTION_HELP, false, 1],
            'long-help start trailing' => [[CommandLineOptions::ACTION_START, '--help'], CommandLineOptions::ACTION_HELP, false, 1],
            // --daemonize
            'short-daemonize' => [[CommandLineOptions::ACTION_START, '-d'], CommandLineOptions::ACTION_START, true, 1],
            'long-daemonize' => [[CommandLineOptions::ACTION_START, '--daemonize'], CommandLineOptions::ACTION_START, true, 1],
            // --num_workers
            'short-num_workers' => [[CommandLineOptions::ACTION_START, '-n', '5'], CommandLineOptions::ACTION_START, false, 5],
            'short-num_workers with equals' => [[CommandLineOptions::ACTION_START, '-n=5'], CommandLineOptions::ACTION_START, false, 5],
            'long-num_workers' => [[CommandLineOptions::ACTION_START, '--num_workers', '5'], CommandLineOptions::ACTION_START, false, 5],
            'long-num_workers with equals' => [[CommandLineOptions::ACTION_START, '--num_workers=5'], CommandLineOptions::ACTION_START, false, 5],
            // full start invocation
            'full start trailing options daemonize first' => [[CommandLineOptions::ACTION_START, '-d', '-n', '5'], CommandLineOptions::ACTION_START, true, 5],
            'full start trailing options daemonize last' => [[CommandLineOptions::ACTION_START, '-n', '5', '-d'], CommandLineOptions::ACTION_START, true, 5],
            'full start leading options daemonize first' => [['-d', '--num_workers', '5', CommandLineOptions::ACTION_START], CommandLineOptions::ACTION_START, true, 5],
            'full start leading options daemonize last' => [['--num_workers', '5', '--daemonize', CommandLineOptions::ACTION_START], CommandLineOptions::ACTION_START, true, 5],
            'full start daemonize start num_workers' => [['--daemonize', CommandLineOptions::ACTION_START, '--num_workers=5'], CommandLineOptions::ACTION_START, true, 5],
            'full start num_workers start daemonize' => [['--num_workers', '5', CommandLineOptions::ACTION_START, '-d'], CommandLineOptions::ACTION_START, true, 5],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider validCommandLines
     */
    public function testParseReturnsCommandLineOptionsWithExpectedValues(
        array $args,
        string $expectedAction,
        bool $expectedDaemonizeValue,
        int $expectedNumberOfWorkers
    ) {
        array_unshift($args, 'command');
        $commandLine = new CommandLine($args);

        $options = $commandLine->parse();

        $this->assertInstanceOf(CommandLineOptions::class, $options);
        $this->assertSame($expectedAction, $options->getAction());
        $this->assertSame($expectedDaemonizeValue, $options->daemonize());
        $this->assertSame($expectedNumberOfWorkers, $options->getNumberOfWorkers());
    }
}
