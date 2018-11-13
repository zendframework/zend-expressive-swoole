<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Command;

use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Swoole\PidManager;

class StartCommand extends Command
{
    use IsRunningTrait;

    public const DEFAULT_NUM_WORKERS = 4;

    public const HELP = <<< 'EOH'
Start the web server. If --daemonize is provided, starts the server as a
background process and returns handling to the shell; otherwise, the
server runs in the current process.

Use --num-workers to control how many worker processes to start. If you
do not provide the option, 4 workers will be started.
EOH;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container, string $name = 'start')
    {
        $this->container = $container;
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
        $this->pidManager = $this->container->get(PidManager::class);
        if ($this->isRunning()) {
            $output->writeln('<error>Server is already running!</error>');
            return 1;
        }

        $server = $this->container->get(SwooleHttpServer::class);
        $server->set([
            'daemonize' => $input->getOption('daemonize'),
            'worker_num' => $input->getOption('num-workers') ?? self::DEFAULT_NUM_WORKERS,
        ]);

        /** @var \Zend\Expressive\Application $app */
        $app = $this->container->get(Application::class);

        /** @var \Zend\Expressive\MiddlewareFactory $factory */
        $factory = $this->container->get(MiddlewareFactory::class);

        // Execute programmatic/declarative middleware pipeline and routing
        // configuration statements
        (require 'config/pipeline.php')($app, $factory, $this->container);
        (require 'config/routes.php')($app, $factory, $this->container);

        // Run the application
        $app->run();

        return 0;
    }
}
