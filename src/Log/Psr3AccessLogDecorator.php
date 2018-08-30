<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Log;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

class Psr3AccessLogDecorator implements AccessLogInterface
{
    /**
     * @var AccessLogFormatter
     */
    private $formatter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Whether or not to look up remote host names when preparing the access
     * log message
     *
     * @var bool
     */
    private $useHostnameLookups;

    public function __construct(
        LoggerInterface $logger,
        AccessLogFormatterInterface $formatter,
        bool $useHostnameLookups = false
    ) {
        $this->logger = $logger;
        $this->formatter = $formatter;
        $this->useHostnameLookups = $useHostnameLookups;
    }

    public function logAccessForStaticResource(Request $request, StaticResourceResponse $response) : void
    {
        $message = $this->formatter->format(
            AccessLogDataMap::createWithStaticResource($request, $response, $this->useHostnameLookups)
        );
        $response->getStatus() >= 400
            ? $this->logger->error($message)
            : $this->logger->info($message);
    }

    public function logAccessForPsr7Resource(Request $request, ResponseInterface $response) : void
    {
        $message = $this->formatter->format(
            AccessLogDataMap::createWithPsrResponse($request, $response, $this->useHostnameLookups)
        );
        $response->getStatusCode() >= 400
            ? $this->logger->error($message)
            : $this->logger->info($message);
    }

    /**
     * {@inheritDoc}
     */
    public function emergency($message, array $context = [])
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function alert($message, array $context = [])
    {
        $this->logger->alert($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function critical($message, array $context = [])
    {
        $this->logger->critical($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function warning($message, array $context = [])
    {
        $this->logger->warning($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function notice($message, array $context = [])
    {
        $this->logger->notice($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function info($message, array $context = [])
    {
        $this->logger->info($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function debug($message, array $context = [])
    {
        $this->logger->debug($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }
}
