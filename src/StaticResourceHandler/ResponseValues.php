<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

class ResponseValues
{
    /**
     * @var array[string, string]
     */
    private $headers = [];

    /**
     * @var bool
     */
    private $sendContent = true;

    /**
     * @var int
     */
    private $status;

    public function __construct(int $status = 200, array $headers = [], bool $sendContent = true)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->sendContent = $sendContent;
    }

    public function addHeader(string $name, string $value) : void
    {
        $this->headers[$name] = $value;
    }

    public function disableContent() : void
    {
        $this->sendContent = false;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function getStatus() : int
    {
        return $this->status;
    }

    public function shouldSendContent() : bool
    {
        return $this->sendContent;
    }

    public function setStatus(int $status) : void
    {
        $this->status = $status;
    }
}
