<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Zend\Expressive\Swoole\Exception;

use function count;
use function explode;
use function fclose;
use function feof;
use function fopen;
use function sprintf;
use function stream_filter_append;
use function trim;

use const STREAM_FILTER_READ;
use const ZLIB_ENCODING_DEFLATE;
use const ZLIB_ENCODING_GZIP;

class GzipMiddleware implements MiddlewareInterface
{
    /**
     * @var array[int, string]
     */
    public const COMPRESSION_CONTENT_ENCODING_MAP = [
        ZLIB_ENCODING_DEFLATE => 'deflate',
        ZLIB_ENCODING_GZIP => 'gzip',
    ];

    /**
     * @var int
     */
    private $compressionLevel;

    /**
     * @param int $compressionLevel Compression level to use. Values less than
     *     1 indicate no compression should occur.
     * @throws Exception\InvalidArgumentException for $compressionLevel values
     *     greater than 9.
     */
    public function __construct(int $compressionLevel = 0)
    {
        if ($compressionLevel > 9) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s only allows compression levels up to 9; received %d',
                __CLASS__,
                $compressionLevel
            ));
        }
        $this->compressionLevel = $compressionLevel;
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(Request $request, string $filename, callable $next): StaticResourceResponse
    {
        $response = $next($request, $filename);

        if (! $this->shouldCompress($request)) {
            return $response;
        }

        $compressionEncoding = $this->getCompressionEncoding($request);
        if (null === $compressionEncoding) {
            return $response;
        }

        $response->setResponseContentCallback(
            function (Response $response, string $filename) use ($compressionEncoding) : void {
                $response->header(
                    'Content-Encoding',
                    GzipMiddleware::COMPRESSION_CONTENT_ENCODING_MAP[$compressionEncoding],
                    true
                );
                $response->header('Connection', 'close', true);

                $handle = fopen($filename, 'rb');
                $params = [
                    'level' => $this->compressionLevel,
                    'window' => $compressionEncoding,
                    'memory' => 9
                ];
                stream_filter_append($handle, 'zlib.deflate', STREAM_FILTER_READ, $params);

                while (feof($handle) !== true) {
                    $response->write(fgets($handle, 4096));
                }

                fclose($handle);
                $response->end();
            }
        );
        return $response;
    }

    /**
     * Is gzip available for current request
     */
    private function shouldCompress(Request $request): bool
    {
        return $this->compressionLevel > 0
            && isset($request->header['accept-encoding']);
    }

    /**
     * Get gzcompress compression encoding.
     */
    private function getCompressionEncoding(Request $request) : ?int
    {
        foreach (explode(',', $request->header['accept-encoding']) as $acceptEncoding) {
            $acceptEncoding = trim($acceptEncoding);
            if ('gzip' === $acceptEncoding) {
                return ZLIB_ENCODING_GZIP;
            }

            if ('deflate' === $acceptEncoding) {
                return ZLIB_ENCODING_DEFLATE;
            }
        }
        return null;
    }
}
