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
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;

class StopCommand extends Command
{
    public const HELP = <<< 'EOH'
Stop the web server. Kills all worker processes and stops the web server.

This command is only relevant when the server was started using the
--daemonize option.
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
        $this->setDescription('Stop the web server.');
        $this->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if ($this->runner->stopServer()) {
            $output->writeln('<info>Server stopped</info>');
            return 0;
        }

        $output->writeln('<error>Error stopping server; check logs for details</error>');
        return 1;
    }
}
