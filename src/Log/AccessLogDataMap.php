<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 *
 * Parts of this class are derived from middlewares/access-log:
 * @copyright Copyright (c) 2018 Oscar Otero (https://github.com/middlewares/access-log/blob/master/LICENSE)
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\Log;

use Psr\Http\Message\ResponseInterface as PsrResponse;
use Swoole\Http\Request as SwooleHttpRequest;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

use function filter_var;
use function function_exists;
use function getenv;
use function getcwd;
use function gethostbyaddr;
use function gethostname;
use function http_build_query;
use function implode;
use function is_string;
use function microtime;
use function preg_match;
use function round;
use function sprintf;
use function strftime;
use function substr;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

class AccessLogDataMap
{
    private const HOST_PORT_REGEX = '/^(?P<host>.*?)((?<!\]):(?P<port>\d+))?$/';

    /**
     * Timestamp when created, indicating end of request processing.
     *
     * @var float
     */
    private $endTime;

    /**
     * @var SwooleHttpRequest
     */
    private $request;

    /**
     * @var ?PsrResponse
     */
    private $psrResponse;

    /**
     * Whether or not to do a hostname lookup when retrieving the remote host name
     *
     * @var bool
     */
    private $useHostnameLookups;

    /**
     * @var StaticResourceResponse
     */
    private $staticResource;

    public static function createWithPsrResponse(
        SwooleHttpRequest $request,
        PsrResponse $response,
        bool $useHostnameLookups = false
    ) : self {
        $map = new self($request, $useHostnameLookups);
        $map->psrResponse = $response;
        return $map;
    }

    public static function createWithStaticResource(
        SwooleHttpRequest $request,
        StaticResourceResponse $response,
        bool $useHostnameLookups = false
    ) : self {
        $map = new self($request, $useHostnameLookups);
        $map->staticResource = $response;
        return $map;
    }

    /**
     * Client IP address of the request (%a)
     */
    public function getClientIp() : string
    {
        return $this->getLocalIp();
    }

    /**
     * Local IP-address (%A)
     */
    public function getLocalIp() : string
    {
        return $this->getServerParamIp('REMOTE_ADDR');
    }

    /**
     * Filename (%f)
     *
     * @todo We likely need a way of injecting the gateway script, instead of
     *     assuming it's getcwd() . /public/index.php.
     * @todo We likely need a way of injecting the document root, instead of
     *     assuming it's getcwd() . /public.
     */
    public function getFilename() : string
    {
        if ($this->psrResponse) {
            return getcwd() . '/public/index.php';
        }
        return getcwd() . '/public' . $this->getServerParam('PATH_INFO');
    }

    /**
     * Size of the message in bytes, excluding HTTP headers (%B, %b)
     */
    public function getBodySize(string $default) : string
    {
        if ($this->psrResponse) {
            return (string) $this->psrResponse->getBody()->getSize() ?: $default;
        }
        return (string) $this->staticResource->getContentLength() ?: $default;
    }

    /**
     * Remote hostname (%h)
     * Will log the IP address if hostnameLookups is false.
     */
    public function getRemoteHostname() : string
    {
        $ip = $this->getServerParamIp('REMOTE_ADDR');

        return $ip !== '-' && $this->useHostnameLookups
            ? gethostbyaddr($ip)
            : $ip;
    }

    /**
     * The message protocol (%H)
     */
    public function getProtocol() : string
    {
        return $this->getServerParam('server_protocol');
    }

    /**
     * The request method (%m)
     */
    public function getMethod() : string
    {
        return $this->getServerParam('request_method');
    }

    /**
     * Returns a message header
     */
    public function getRequestHeader(string $name) : string
    {
        return $this->request->header[strtolower($name)] ?? '-';
    }

    /**
     * Returns a message header
     */
    public function getResponseHeader(string $name) : string
    {
        if ($this->psrResponse) {
            return $this->psrResponse->getHeaderLine($name) ?: '-';
        }
        return $this->staticResource->getHeader($name) ?: '-';
    }

    /**
     * Returns a environment variable (%e)
     */
    public function getEnv(string $name) : string
    {
        return getenv($name) ?: '-';
    }

    /**
     * Returns a cookie value (%{VARNAME}C)
     */
    public function getCookie(string $name) : string
    {
        return $this->request->cookie[$name] ?? '-';
    }

    /**
     * The canonical port of the server serving the request. (%p)
     */
    public function getPort(string $format) : string
    {
        switch ($format) {
            case 'canonical':
            case 'local':
                preg_match(self::HOST_PORT_REGEX, $this->request->header['host'] ?? '', $matches);
                $port = $matches['port'] ?? null;
                $port = $port ?: $this->getServerParam('server_port', '80');
                $scheme = $this->getServerParam('https', '');
                return $scheme && $port === '80' ? '443' : $port;
            default:
                return '-';
        }
    }

    /**
     * The query string (%q)
     * (prepended with a ? if a query string exists, otherwise an empty string).
     */
    public function getQuery() : string
    {
        $query = $this->request->get;
        return [] === $query ? '' : sprintf('?%s', http_build_query($query));
    }

    /**
     * Status. (%s)
     */
    public function getStatus() : string
    {
        return $this->psrResponse
            ? (string) $this->psrResponse->getStatusCode()
            : (string) $this->staticResource->getStatus();
    }

    /**
     * Remote user if the request was authenticated. (%u)
     */
    public function getRemoteUser() : string
    {
        return $this->getServerParam('REMOTE_USER');
    }

    /**
     * The URL path requested, not including any query string. (%U)
     */
    public function getPath() : string
    {
        return $this->getServerParam('PATH_INFO');
    }

    /**
     * The canonical ServerName of the server serving the request. (%v)
     */
    public function getHost() : string
    {
        return $this->getRequestHeader('host');
    }

    /**
     * The server name according to the UseCanonicalName setting. (%V)
     */
    public function getServerName() : string
    {
        return gethostname();
    }

    /**
     * First line of request. (%r)
     */
    public function getRequestLine() : string
    {
        return sprintf(
            '%s %s%s %s',
            $this->getMethod(),
            $this->getPath(),
            $this->getQuery(),
            $this->getProtocol()
        );
    }

    /**
     * Returns the response status line
     */
    public function getResponseLine() : string
    {
        $reasonPhrase = '';
        if ($this->psrResponse && $this->psrResponse->getReasonPhrase()) {
            $reasonPhrase .= sprintf(' %s', $this->psrResponse->getReasonPhrase());
        }
        return sprintf(
            '%s %d%s',
            $this->getProtocol(),
            $this->getStatus(),
            $reasonPhrase
        );
    }

    /**
     * Bytes transferred (received and sent), including request and headers (%S)
     */
    public function getTransferredSize() : string
    {
        return (string) ($this->getRequestMessageSize(0) + $this->getResponseMessageSize(0)) ?: '-';
    }

    /**
     * Get the request message size (including first line and headers)
     */
    public function getRequestMessageSize($default = null) : ?int
    {
        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        $bodySize = $strlen($this->request->rawContent());

        if (null === $bodySize) {
            return $default;
        }

        $firstLine = $this->getRequestLine();

        $headers = [];

        foreach ($this->request->header as $header => $value) {
            if (is_string($value)) {
                $headers[] = sprintf('%s: %s', $header, $value);
                continue;
            }

            foreach ($value as $line) {
                $headers[] = sprintf('%s: %s', $header, $line);
            }
        }

        $headersSize = $strlen(implode("\r\n", $headers));

        return $strlen($firstLine) + 2 + $headersSize + 4 + $bodySize;
    }

    /**
     * Get the response message size (including first line and headers)
     */
    public function getResponseMessageSize($default = null) : ?int
    {
        $bodySize = $this->psrResponse
            ? $this->psrResponse->getBody()->getSize()
            : $this->staticResource->getContentLength();

        if (null === $bodySize) {
            return $default;
        }

        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $firstLineSize = $strlen($this->getResponseLine($message));

        $headerSize = $this->psrResponse
            ? $this->getPsrResponseHeaderSize()
            : $this->staticResource->getHeaderSize();

        return $firstLineSize + 2 + $headerSize + 4 + $bodySize;
    }

    /**
     * Returns the request time (%t, %{format}t)
     */
    public function getRequestTime(string $format) : string
    {
        $begin = $this->getServerParam('request_time_float');
        $time = $begin;

        if (strpos($format, 'begin:') === 0) {
            $format = substr($format, 6);
        } elseif (strpos($format, 'end:') === 0) {
            $time = $this->endTime;
            $format = substr($format, 4);
        }

        switch ($format) {
            case 'sec':
                return sprintf('[%s]', round($time));
            case 'msec':
                return sprintf('[%s]', round($time * 1E3));
            case 'usec':
                return sprintf('[%s]', round($time * 1E6));
            default:
                return sprintf('[%s]', strftime($format, (int) $time));
        }
    }

    /**
     * The time taken to serve the request. (%T, %{format}T)
     */
    public function getRequestDuration(string $format) : string
    {
        $begin = $this->getServerParam('request_time_float');
        switch ($format) {
            case 'us':
                return (string) round(($this->endTime - $begin) * 1E6);
            case 'ms':
                return (string) round(($this->endTime - $begin) * 1E3);
            default:
                return (string) round($this->endTime - $begin);
        }
    }

    private function __construct(SwooleHttpRequest $request, bool $useHostnameLookups)
    {
        $this->endTime = microtime(true);
        $this->request = $request;
        $this->useHostnameLookups = $useHostnameLookups;
    }

    /**
     * Returns an server parameter value
     */
    private function getServerParam(string $key, string $default = '-') : string
    {
        return $this->request->server[strtolower($key)] ?? $default;
    }

    /**
     * Returns an ip from the server params
     */
    private function getServerParamIp(string $key) : string
    {
        $ip = $this->getServerParam($key);

        return false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
            ? '-'
            : $ip;
    }

    private function getPsrResponseHeaderSize() : int
    {
        if (! $this->psrResponse) {
            return 0;
        }

        $headers = [];

        foreach ($this->psrResponse->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                $headers[] = sprintf('%s: %s', $header, $value);
            }
        }

        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        return $strlen(implode("\r\n", $headers));
    }
}
