<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function printf;
use function sprintf;
use function str_replace;

use const PHP_EOL;

/**
 * Default logger for logging server start and requests.
 *
 * PSR-3 logger implementation that logs to STDOUT, using a newline after each
 * message. Priority is ignored.
 *
 * @internal
 */
class StdoutLogger implements LoggerInterface
{
    /**
     * {@inheritDoc}
     */
    public function emergency($message, array $context = [])
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function alert($message, array $context = [])
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function critical($message, array $context = [])
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function error($message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function warning($message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function notice($message, array $context = [])
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function info($message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function debug($message, array $context = [])
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = [])
    {
        foreach ($context as $key => $value) {
            $search = sprintf('{%s}', $key);
            $message = str_replace($search, $value, $message);
        }
        printf('%s%s', $message, PHP_EOL);
    }
}
