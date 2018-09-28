<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleHttpResponse;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\Emitter\SapiEmitterTrait;

class SwooleEmitter implements EmitterInterface
{
    use SapiEmitterTrait;

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    const CHUNK_SIZE = 2097152; // 2 MB

    /**
     * @var SwooleHttpResponse
     */
    private $swooleResponse;

    public function __construct(SwooleHttpResponse $response)
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
        if (PHP_SAPI !== 'cli' || ! extension_loaded('swoole')) {
            return false;
        }
        $this->emitStatusCode($response);
        $this->emitHeaders($response);
        $this->emitCookies($response);
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
        foreach ($response->withoutHeader(SetCookies::SET_COOKIE_HEADER)->getHeaders() as $name => $values) {
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

    /**
     * Emit the cookies
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitCookies(ResponseInterface $response): void
    {
        foreach (SetCookies::fromResponse($response)->getAll() as $cookie) {
            $this->swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpires(),
                $cookie->getPath() ?: '/',
                $cookie->getDomain() ?: '',
                $cookie->getSecure(),
                $cookie->getHttpOnly()
            );
        }
    }
}
