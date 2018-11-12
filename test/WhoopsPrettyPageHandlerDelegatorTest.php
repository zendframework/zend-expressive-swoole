<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Whoops\Handler\PrettyPageHandler;
use Zend\Expressive\Container\WhoopsPageHandlerFactory;
use Zend\Expressive\Swoole\ConfigProvider;
use Zend\ServiceManager\ServiceManager;

class WhoopsPrettyPageHandlerDelegatorTest extends TestCase
{
    /** @var ServiceManager */
    private $container;

    public function setUp()
    {
        parent::setUp();

        $dependencies = (new ConfigProvider())()['dependencies'];
        // @see https://github.com/zendframework/zend-expressive-skeleton/blob/master/src/ExpressiveInstaller/Resources/config/error-handler-whoops.php
        $dependencies['factories']['Zend\Expressive\WhoopsPageHandler'] = WhoopsPageHandlerFactory::class;
        $this->container = new ServiceManager($dependencies);
    }

    public function testDefaultConfigurationDecoratesPageHandler() : void
    {
        $handler = $this->container->get('Zend\Expressive\WhoopsPageHandler');
        $this->assertInstanceOf(PrettyPageHandler::class, $handler);
        $this->assertTrue($handler->handleUnconditionally());
    }
}
