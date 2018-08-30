<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Log;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Create and return an access logger.
 *
 * Uses the Psr\Log\LoggerInterface to seed a Psr3AccessLogDecorator instance,
 * falling back to the shipped StdoutLogger if none is present. Additionally,
 * it will look for and use the following configuration, if found:
 *
 * <code>
 * 'zend-expressive-swoole' => [
 *     'swoole-http-server' => [
 *         'logger' => [
 *             'format' => string, // one of the AccessLogFormatter::FORMAT_* constants
 *             'use-hostname-lookups' => bool, // Set to true to enable hostname lookups
 *         ],
 *     ],
 * ],
 * </code>
 */
class AccessLogFactory
{
    public function __invoke(ContainerInterface $container) : AccessLogInterface
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config['zend-expressive-swoole']['swoole-http-server']['logger'] ?? [];

        return new Psr3AccessLogDecorator(
            $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : new StdoutLogger(),
            $this->getFormatter($container, $config),
            $config['use-hostname-lookups'] ?? false
        );
    }

    private function getFormatter(ContainerInterface $container, array $config) : AccessLogFormatterInterface
    {
        if ($container->has(AccessLogFormatterInterface::class)) {
            return $container->get(AccessLogFormatterInterface::class);
        }

        return new AccessLogFormatter(
            $config['format'] ?? AccessLogFormatter::FORMAT_COMMON
        );
    }
}
