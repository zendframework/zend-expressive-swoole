<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sleep;

use const SWOOLE_PROCESS;

class ReloadCommand extends Command
{
    public const HELP = <<< 'EOH'
Reload the web server. Sends a SIGUSR1 signal to master process and reload
all worker processes.

This command is only relevant when the server was started using the
--daemonize option, and the zend-expressive-swoole.swoole-http-server.mode
configuration value is set to SWOOLE_PROCESS.
EOH;

    /**
     * @var int
     */
    private $serverMode;

    public function __construct(int $serverMode, string $name = 'reload')
    {
        $this->serverMode = $serverMode;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $this->setDescription('Reload the web server.');
        $this->setHelp(self::HELP);
        $this->addOption(
            'num-workers',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of worker processes to use after reloading.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if ($this->serverMode !== SWOOLE_PROCESS) {
            $output->writeln(
                '<error>Server is not configured to run in SWOOLE_PROCESS mode;'
                . ' cannot reload</error>'
            );
            return 1;
        }

        $output->writeln('<info>Reloading server ...</info>');

        $application = $this->getApplication();

        $stop   = $application->find('stop');
        $result = $stop->run(new ArrayInput([
            'command' => 'stop',
        ]), $output);

        if (0 !== $result) {
            $output->writeln('<error>Cannot reload server: unable to stop current server</error>');
            return $result;
        }

        $output->write('<info>Waiting for 5 seconds to ensure server is stopped...</info>');
        for ($i = 0; $i < 5; $i += 1) {
            $output->write('<info>.</info>');
            sleep(1);
        }
        $output->writeln('<info>[DONE]</info>');
        $output->writeln('<info>Starting server</info>');

        $start  = $application->find('start');
        $result = $start->run(new ArrayInput([
            'command'       => 'start',
            '--daemonize'   => true,
            '--num-workers' => $input->getOption('num-workers') ?? StartCommand::DEFAULT_NUM_WORKERS,
        ]), $output);

        if (0 !== $result) {
            $output->writeln('<error>Cannot reload server: unable to start server</error>');
            return $result;
        }

        return 0;
    }
}
