<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Swoole\Process as SwooleProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Swoole\PidManager;

use function time;
use function usleep;

class StopCommand extends Command
{
    use IsRunningTrait;

    public const HELP = <<< 'EOH'
Stop the web server. Kills all worker processes and stops the web server.

This command is only relevant when the server was started using the
--daemonize option.
EOH;

    /**
     * @var PidManager
     */
    private $pidManager;

    public function __construct(PidManager $pidManager, string $name = 'stop')
    {
        $this->pidManager = $pidManager;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $this->setDescription('Stop the web server.');
        $this->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if (! $this->isRunning()) {
            $output->writeln('<info>Server is not running</info>');
            return 0;
        }

        $output->writeln('<info>Stopping server ...</info>');

        if (! $this->stopServer()) {
            $output->writeln('<error>Error stopping server; check logs for details</error>');
            return 1;
        }

        $output->writeln('<info>Server stopped</info>');
        return 0;
    }

    private function stopServer() : bool
    {
        [$masterPid, ] = $this->pidManager->read();
        $startTime     = time();
        $result        = SwooleProcess::kill((int) $masterPid);

        while (! $result) {
            if (! SwooleProcess::kill((int) $masterPid, 0)) {
                continue;
            }
            if (time() - $startTime >= 60) {
                $result = false;
                break;
            }
            usleep(10000);
        }

        if (! $result) {
            return false;
        }

        $this->pidManager->delete();

        return true;
    }
}
