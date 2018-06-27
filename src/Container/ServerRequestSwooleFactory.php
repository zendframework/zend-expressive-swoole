<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Container;

use Psr\Container\ContainerInterface;
use swoole_http_request;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Swoole\Stream\SwooleStream;

use function Zend\Diactoros\parseCookieHeader;
use function Zend\Diactoros\marshalMethodFromSapi;
use function Zend\Diactoros\marshalProtocolVersionFromSapi;
use function Zend\Diactoros\marshalUriFromSapi;
use function Zend\Diactoros\normalizeUploadedFiles;

/**
 * Return a factory for generating a server request from Swoole.
 */
class ServerRequestSwooleFactory
{
    public function __invoke(ContainerInterface $container) : callable
    {
        return function (swoole_http_request $request) {
            // Aggregate values from Swoole request object
            $get     = $request->get ?? [];
            $post    = $request->post ?? [];
            $cookie  = $request->cookie ?? [];
            $files   = $request->files ?? [];
            $server  = $request->server ?? [];
            $headers = $request->header ?? [];

            // Create request
            $request = new ServerRequest(
                $server,
                normalizeUploadedFiles($files),
                marshalUriFromSapi($server, $headers),
                marshalMethodFromSapi($server),
                new SwooleStream($request),
                $headers,
                $cookie,
                $get,
                $post,
                marshalProtocolVersionFromSapi($server)
            );

            if (empty($cookie) && $request->hasHeader('Cookie')) {
                $request = $request->withCookieParams(
                    parseCookieHeader($request->getHeaderLine('Cookie'))
                );
            }

            return $request;
        };
    }
}
