<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Emitter;

use Psr\Http\Message\ResponseInterface;
use swoole_http_response;
use Zend\Diactoros\Response\SapiEmitterTrait;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;

class SwooleEmitter implements EmitterInterface
{
    use SapiEmitterTrait;

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    const CHUNK_SIZE = 2097152; // 2 MB

    /**
     * @var SwooleResponse
     */
    private $swooleResponse;

    public function __construct(swoole_http_response $response)
    {
        $this->swooleResponse = $response;
    }

    /**
     * Emits a response for the Swoole environment.
     *
     * @return void
     */
    public function emit(ResponseInterface $response) : bool
    {
        if (PHP_SAPI !== 'cli' || !extension_loaded('swoole')) {
            return false;
        }
        $this->emitStatusCode($response);
        $this->emitHeaders($response);
        $this->emitBody($response);
        return true;
    }

    /**
     * Emit the status code
     *
     * @return void
     */
    private function emitStatusCode(ResponseInterface $response)
    {
        $this->swooleResponse->status($response->getStatusCode());
    }

    /**
     * Emit the headers
     *
     * @return void
     */
    private function emitHeaders(ResponseInterface $response)
    {
        foreach ($response->getHeaders() as $name => $values) {
            $name = $this->filterHeader($name);
            $this->swooleResponse->header($name, implode(', ', $values));
        }
    }

    /**
     * Emit the message body.
     *
     * @return void
     */
    private function emitBody(ResponseInterface $response)
    {
        $body = $response->getBody();
        $body->rewind();

        if ($body->getSize() <= static::CHUNK_SIZE) {
            $this->swooleResponse->end($body->getContents());
            return;
        }

        while (! $body->eof()) {
            $this->swooleResponse->write($body->read(static::CHUNK_SIZE));
        }
        $this->swooleResponse->end();
    }
}
