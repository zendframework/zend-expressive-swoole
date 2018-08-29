<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;

class StartCommand extends Command
{
    public const HELP = <<< 'EOH'
Start the web server. If --daemonize is provided, starts the server as a
background process and returns handling to the shell; otherwise, the
server runs in the current process.

Use --num-workers to control how many worker processes to start. If you
do not provide the option, 4 workers will be started.
EOH;

    /**
     * @var SwooleRequestHandlerRunner
     */
    private $runner;

    public function __construct(SwooleRequestHandlerRunner $runner, string $name = null)
    {
        $this->runner = $runner;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $this->setDescription('Start the web server.');
        $this->setHelp(self::HELP);
        $this->addOption(
            'daemonize',
            'd',
            InputOption::VALUE_NONE,
            'Daemonize the web server (run as a background process).'
        );
        $this->addOption(
            'num-workers',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of worker processes to use.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->runner->startServer([
            'daemonize' => $input->getOption('daemonize'),
            'worker_num' => $input->getOption('num-workers') ?? 4,
        ]);

        return 0;
    }
}
