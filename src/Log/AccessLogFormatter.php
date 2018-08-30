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

use function preg_replace_callback;

class AccessLogFormatter implements AccessLogFormatterInterface
{
    /**
     * @link http://httpd.apache.org/docs/2.4/mod/mod_log_config.html#examples
     */
    public const FORMAT_COMMON = '%h %l %u %t "%r" %>s %b';
    public const FORMAT_COMMON_VHOST = '%v %h %l %u %t "%r" %>s %b';
    public const FORMAT_COMBINED = '%h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i"';
    public const FORMAT_REFERER = '%{Referer}i -> %U';
    public const FORMAT_AGENT = '%{User-Agent}i';

    /**
     * @link https://httpd.apache.org/docs/2.4/logs.html#virtualhost
     */
    public const FORMAT_VHOST = '%v %l %u %t "%r" %>s %b';

    /**
     * @link https://anonscm.debian.org/cgit/pkg-apache/apache2.git/tree/debian/config-dir/apache2.conf.in#n212
     * @codingStandardsIgnoreStart
     * phpcs:disable
     */
    public const FORMAT_COMMON_DEBIAN = '%h %l %u %t “%r” %>s %O';
    public const FORMAT_COMBINED_DEBIAN = '%h %l %u %t “%r” %>s %O “%{Referer}i” “%{User-Agent}i”';
    public const FORMAT_VHOST_COMBINED_DEBIAN = '%v:%p %h %l %u %t “%r” %>s %O “%{Referer}i” “%{User-Agent}i"';
    // @codingStandardsIgnoreEnd
    // phpcs:enable

    /**
     * Message format to use when generating a log message.
     *
     * @var string
     */
    private $format;

    public function __construct(string $format = self::FORMAT_COMMON)
    {
        $this->format = $format;
    }

    /**
     * Transform a log format to the final string to log.
     */
    public function format(AccessLogDataMap $map) : string
    {
        $message = $this->replaceConstantDirectives($this->format, $map);
        $message = $this->replaceVariableDirectives($message, $map);
        return $message;
    }

    private function replaceConstantDirectives(
        string $format,
        AccessLogDataMap $map
    ) : string {
        return preg_replace_callback(
            '/%(?:[<>])?([%aABbDfhHklLmpPqrRstTuUvVXIOS])/',
            function (array $matches) use ($map) {
                switch ($matches[1]) {
                    case '%':
                        return '%';
                    case 'a':
                        return $map->getClientIp();
                    case 'A':
                        return $map->getLocalIp();
                    case 'B':
                        return $map->getBodySize('0');
                    case 'b':
                        return $map->getBodySize('-');
                    case 'D':
                        return $map->getRequestDuration('ms');
                    case 'f':
                        return $map->getFilename();
                    case 'h':
                        return $map->getRemoteHostname();
                    case 'H':
                        return $map->getProtocol();
                    case 'm':
                        return $map->getMethod();
                    case 'p':
                        return $map->getPort('canonical');
                    case 'q':
                        return $map->getQuery();
                    case 'r':
                        return $map->getRequestLine();
                    case 's':
                        return $map->getStatus();
                    case 't':
                        return $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');
                    case 'T':
                        return $map->getRequestDuration('s');
                    case 'u':
                        return $map->getRemoteUser();
                    case 'U':
                        return $map->getPath();
                    case 'v':
                        return $map->getHost();
                    case 'V':
                        return $map->getServerName();
                    case 'I':
                        return $map->getRequestMessageSize('-');
                    case 'O':
                        return $map->getResponseMessageSize('-');
                    case 'S':
                        return $map->getTransferredSize();
                    //NOT IMPLEMENTED
                    case 'k':
                    case 'l':
                    case 'L':
                    case 'P':
                    case 'R':
                    case 'X':
                    default:
                        return '-';
                }
            },
            $format
        );
    }

    private function replaceVariableDirectives(
        string $format,
        AccessLogDataMap $map
    ): string {
        return preg_replace_callback(
            '/%(?:[<>])?{([^}]+)}([aCeinopPtT])/',
            function (array $matches) use ($map) {
                switch ($matches[2]) {
                    case 'a':
                        return $map->getClientIp();
                    case 'C':
                        return $map->getCookie($matches[1]);
                    case 'e':
                        return $map->getEnv($matches[1]);
                    case 'i':
                        return $map->getRequestHeader($matches[1]);
                    case 'o':
                        return $map->getResponseHeader($matches[1]);
                    case 'p':
                        return $map->getPort($matches[1]);
                    case 't':
                        return $map->getRequestTime($matches[1]);
                    case 'T':
                        return $map->getRequestDuration($matches[1]);
                    //NOT IMPLEMENTED
                    case 'n':
                    case 'P':
                    default:
                        return '-';
                }
            },
            $format
        );
    }
}
