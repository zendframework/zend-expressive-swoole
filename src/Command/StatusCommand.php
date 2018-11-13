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

class StatusCommand extends Command
{
    use IsRunningTrait;

    public const HELP = <<< 'EOH'
Find out if the server is running.

This command is only relevant when the server was started using the
--daemonize option.
EOH;

    /**
     * @var PidManager
     */
    private $pidManager;

    public function __construct(PidManager $pidManager, string $name = 'status')
    {
        $this->pidManager = $pidManager;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $this->setDescription('Get the status of the web server.');
        $this->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $message = $this->isRunning()
            ? '<info>Server is running</info>'
            : '<info>Server is not running</info>';

        $output->writeln($message);

        return 0;
    }
}
