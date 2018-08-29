<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Response as SwooleHttpResponse;

class StaticResourceResponse
{
    /**
     * @var array[string, string]
     */
    private $headers = [];

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
                // Lower-case is used here for consistency with requests; aids with logging
                $response->header('content-length', (string) filesize($filename), true);
                $response->sendfile($filename);
            };
    }

    public function addHeader(string $name, string $value) : void
    {
        $this->headers[$name] = $value;
    }

    public function disableContent() : void
    {
        $this->sendContent = false;
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
