<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use stdClass;

use function ctype_digit;

class CommandLine
{
    private const TYPE_FLAG = 0b0001;
    private const TYPE_INT  = 0b0010;

    private const MESSAGE_HELP = <<< 'EOH'
Usage:
  %s [options] [<argument>]

Arguments:
  help                Display help for the command (this message)
  start               Start the web server (this is the default if no
                      argument is given)
  stop                Stop the web server

Options:
  -d, --daemonize     Daemonize the web server when starting (run as a
                      background process)
  -h, --help          Display this message
  -n, --num_workers   Set the number of worker processes; defaults to 1

Help:
  This command allows you to control the web server, including starting
  and stopping it. The provided options --daemonize and --num_workers
  allow you to control how the server operates when you start it; other
  options may be provided via configuration; see the documentation for
  all options:

  https://docs.zendframework.com/zend-expressive-swoole/intro/#providing-additional-swoole-configuration

  The --daemonize and --num_workers options are only relevant when calling
  "start".

EOH;

    /**
     * @var string[]
     */
    private $allowedActions = [
        CommandLineOptions::ACTION_HELP,
        CommandLineOptions::ACTION_START,
        CommandLineOptions::ACTION_STOP,
        CommandLineOptions::ACTION_RELOAD,
    ];

    /**
     * @var ?string
     */
    private $argument;

    /**
     * @var string[]
     */
    private $arguments;

    /**
     * @var string
     */
    private $commandName;

    /**
     * @var array[string, mixed]
     */
    private $defaultOptions = [
        'action'      => CommandLineOptions::ACTION_START,
        'daemonize'   => false,
        'help'        => false,
        'num_workers' => 1,
    ];

    /**
     * @var callable
     */
    private $exit;

    /**
     * @var array[string, mixed]
     */
    private $options = [];

    /**
     * @var array[string, int]
     */
    private $optionMap = [
        'daemonize'   => self::TYPE_FLAG,
        'help'        => self::TYPE_FLAG,
        'num_workers' => self::TYPE_INT,
    ];

    /**
     * @var array[string, string]
     */
    private $shortOptionMap = [
        'd' => 'daemonize',
        'h' => 'help',
        'n' => 'num_workers',
    ];

    /**
     * @var resource
     */
    private $stderrStream = STDERR;

    /**
     * @var resource
     */
    private $stdoutStream = STDOUT;

    /**
     * @throws Exception\RuntimeException if unable to discover CLI arguments
     *     (e.g., if $argv is not registered).
     */
    public function __construct(array $arguments = null)
    {
        $arguments = $arguments ?: $this->discoverArguments();
        $this->commandName = array_shift($arguments);
        $this->arguments = $arguments;

        // This allows us to mock the exit built-in when testing
        $this->exit = function (int $status) : void {
            exit($status);
        };
    }

    public function parse() : ?CommandLineOptions
    {
        $args = $this->arguments;
        $options = $this->defaultOptions;
        $argument = null;

        while (count($args) > 0) {
            if (substr($args[0], 0, 2) == '--') {
                $parsed = $this->parseOption($options, $args);
                if (! $parsed) {
                    return null;
                }

                $options = $parsed->options;
                $args = $parsed->args;
                continue;
            }

            if (substr($args[0], 0, 1) == '-') {
                $parsed = $this->parseOption($options, $args);
                if (! $parsed) {
                    return null;
                }

                $args = $parsed->args;
                $options = $parsed->options;
                continue;
            }

            if (in_array($args[0], $this->allowedActions, true)
                && null === $argument
            ) {
                $options['action'] = array_shift($args);
                continue;
            }

            $this->emitHelpAndExit(
                1,
                sprintf('An unexpected argument was encountered: %s', $args[0])
            );
            return null;
        }

        if ($options['help']) {
            $options['action'] = CommandLineOptions::ACTION_HELP;
        }

        return new CommandLineOptions(
            $options['action'],
            $options['daemonize'],
            $options['num_workers']
        );
    }

    /**
     * Emit the help message and exit.
     *
     * Uses the method's $status argument as the exit code.
     *
     * THIS METHOD HAS SIDE EFFECTS:
     *
     * - Writes to either STDOUT or STDERR
     * - Calls exit()
     */
    public function emitHelpAndExit(int $status = 0, string $errorMessage = null) : void
    {
        $message = str_replace("\n", PHP_EOL, self::MESSAGE_HELP);
        $message = sprintf($message, $this->commandName);

        if ($errorMessage) {
            $message = sprintf('Error:%3$s  %1$s%3$s%3$s%2$s', $errorMessage, $message, PHP_EOL);
        }

        $status === 0
            ? fwrite($this->stdoutStream, $message)
            : fwrite($this->stderrStream, $message);

        $exit = $this->exit;
        $exit($status);
    }

    /**
     * Set the function to use when exiting. This exists solely for unit
     * testing purposes.
     *
     * @internal
     */
    public function setExitFunction(callable $exit) : void
    {
        $this->exit = $exit;
    }

    /**
     * Set the stream to use for STDERR. This exists solely for unit testing
     * purposes.
     *
     * @internal
     * @throws Exception\InvalidArgumentException
     */
    public function setStderrStream($stream) : void
    {
        if (! is_resource($stream)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'STDERR stream MUST be a resource; received %s',
                is_object($stream) ? get_class($stream) : gettype($stream)
            ));
        }

        $this->stderrStream = $stream;
    }

    /**
     * Set the stream to use for STDOUT. This exists solely for unit testing
     * purposes.
     *
     * @internal
     * @throws Exception\InvalidArgumentException
     */
    public function setStdoutStream($stream) : void
    {
        if (! is_resource($stream)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'STDOUT stream MUST be a resource; received %s',
                is_object($stream) ? get_class($stream) : gettype($stream)
            ));
        }

        $this->stdoutStream = $stream;
    }

    /**
     * @throws Exception\RuntimeException if unable to discover CLI arguments
     *     (e.g., if $argv is not registered).
     */
    private function discoverArguments() : array
    {
        if (isset($_SERVER['argv'])) {
            return $_SERVER['argv'];
        }

        global $argv;

        if (is_array($argv)) {
            return $argv;
        }

        throw new Exception\RuntimeException('Unable to discover CLI arguments');
    }

    private function parseOption(array $options, array $args) : ?stdClass
    {
        $optionWithParam = ltrim(array_shift($args), '-');
        $option = $optionWithParam;
        if (false !== strpos($optionWithParam, '=')) {
            [$option, $value] = explode('=', $optionWithParam, 2);
            array_unshift($args, $value);
        }

        if (in_array($option, array_keys($this->shortOptionMap), true)) {
            $option = $this->shortOptionMap[$option];
        }

        if (! in_array($option, array_keys($this->optionMap), true)) {
            $this->emitHelpAndExit(
                1,
                sprintf('An unexpected option was encountered: %s', $option)
            );
            return null;
        }

        switch ($this->optionMap[$option]) {
            case self::TYPE_FLAG:
                $options[$option] = true;
                break;
            case self::TYPE_INT:
                $value = array_shift($args);
                if (! ctype_digit($value)) {
                    $this->emitHelpAndExit(
                        1,
                        sprintf('An unexpected value for the option "%s" was encountered: %s', $option, $value)
                    );
                    return null;
                }
                $options[$option] = (int) $value;
                break;
        }

        return (object) [
            'args' => $args,
            'options' => $options,
        ];
    }
}
