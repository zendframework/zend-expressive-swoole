<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Response as SwooleHttpResponse;

use function filesize;
use function function_exists;
use function implode;
use function sprintf;

class StaticResourceResponse
{
    /**
     * @var int
     */
    private $contentLength = 0;

    /**
     * @var array[string, string]
     */
    private $headers = [];

    /**
     * @var bool Does this response represent a failure to locate the requested
     *     resource and/or that it cannot be requested?
     */
    private $isFailure = false;

    /**
     * @var callable
     */
    private $responseContentCallback;

    /**
     * @var bool
     */
    private $sendContent = true;

    /**
     * @var int
     */
    private $status;

    /**
     * @param callable $responseContentCallback Callback to use when emitting
     *     the response body content via Swoole. Must have the signature:
     *     function (SwooleHttpResponse $response, string $filename) : void
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        bool $sendContent = true,
        callable $responseContentCallback = null
    ) {
        $this->status = $status;
        $this->headers = $headers;
        $this->sendContent = $sendContent;
        $this->responseContentCallback = $responseContentCallback
            ?: function (SwooleHttpResponse $response, string $filename) : void {
                $this->contentLength = filesize($filename);
                $response->header('Content-Length', (string)$this->contentLength, true);
                $response->sendfile($filename);
            };
    }

    public function addHeader(string $name, string $value) : void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Retrieve the content length that was sent via sendSwooleResponse()
     *
     * This is exposed to allow logging the content emitted.
     */
    public function getContentLength() : int
    {
        return $this->contentLength;
    }

    public function disableContent() : void
    {
        $this->sendContent = false;
    }

    /**
     * Retrieve a single named header
     *
     * This is exposed to allow logging specific response headers when present.
     */
    public function getHeader(string $name) : string
    {
        return $this->headers[$name] ?? '';
    }

    /**
     * Retrieve the aggregated length of all headers.
     *
     * This is exposed for logging purposes.
     */
    public function getHeaderSize() : int
    {
        $headers = [];
        foreach ($this->headers as $header => $value) {
            $headers[] = sprintf('%s: %s', $header, $value);
        }

        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        return $strlen(implode("\r\n", $headers));
    }

    /**
     * Retrieve the response status code that was sent via sendSwooleResponse()
     *
     * This is exposed to allow logging the status code emitted.
     */
    public function getStatus() : int
    {
        return $this->status;
    }

    /**
     * Can the requested resource be served?
     */
    public function isFailure() : bool
    {
        return $this->isFailure;
    }

    /**
     * Indicate that the requested resource cannot be served.
     *
     * Call this method if the requested resource does not exist, or if it
     * fails certain validation checks.
     */
    public function markAsFailure() : void
    {
        $this->isFailure = true;
    }

    /**
     * Send the Swoole HTTP Response representing the static resource.
     *
     * Emits the status code and all headers, and then uses the composed
     * content callback to emit the content.
     *
     * If content has been disabled, it calls $response->end() instead of the
     * content callback.
     */
    public function sendSwooleResponse(SwooleHttpResponse $response, string $filename) : void
    {
        $response->status($this->status);
        foreach ($this->headers as $header => $value) {
            $response->header($header, $value, true);
        }

        $contentSender = $this->responseContentCallback;

        $this->sendContent ? $contentSender($response, $filename) : $response->end();
    }

    public function setContentLength(int $length) : void
    {
        $this->contentLength = $length;
    }

    /**
     * @param callable $responseContentCallback Callback to use when emitting
     *     the response body content via Swoole. Must have the signature:
     *     function (SwooleHttpResponse $response, string $filename) : void
     */
    public function setResponseContentCallback(callable $callback) : void
    {
        $this->responseContentCallback = $callback;
    }

    public function setStatus(int $status) : void
    {
        $this->status = $status;
    }
}
