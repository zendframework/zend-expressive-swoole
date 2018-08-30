<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Log;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Swoole\Log\AccessLogDataMap;
use Zend\Expressive\Swoole\Log\AccessLogFormatter;

class AccessLogFormatterTest extends TestCase
{
    public function testFormatterDelegatesToDataMapToReplacePlaceholdersInFormat()
    {
        $hostname = gethostname();

        $dataMap = $this->prophesize(AccessLogDataMap::class);
        $dataMap->getClientIp()->willReturn('127.0.0.10'); // %a
        $dataMap->getLocalIp()->willReturn('127.0.0.1'); // %A
        $dataMap->getBodySize('0')->willReturn('1234'); // %B
        $dataMap->getBodySize('-')->willReturn('1234'); // %b
        $dataMap->getRequestDuration('ms')->willReturn('4321'); // %D
        $dataMap->getFilename()->willReturn(__FILE__); // %f
        $dataMap->getRemoteHostname()->willReturn($hostname); // %h
        $dataMap->getProtocol()->willReturn('HTTP/1.1'); // %H
        $dataMap->getMethod()->willReturn('POST'); // %m
        $dataMap->getPort('canonical')->willReturn('9000'); // %p
        $dataMap->getQuery()->willReturn('?foo=bar'); // %q
        $dataMap->getRequestLine()->willReturn('POST /path?foo=bar HTTP/1.1'); // %r
        $dataMap->getStatus()->willReturn('202'); // %s
        $dataMap->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z')->willReturn('[1234567890]'); // %t
        $dataMap->getRequestDuration('s')->willReturn('22'); // %T
        $dataMap->getRemoteUser()->willReturn('expressive'); // %u
        $dataMap->getPath()->willReturn('/path'); // %U
        $dataMap->getHost()->willReturn('expressive.local'); // %v
        $dataMap->getServerName()->willReturn('expressive.local'); // %V
        $dataMap->getRequestMessageSize('-')->willReturn('78'); // %I
        $dataMap->getResponseMessageSize('-')->willReturn('89'); // %O
        $dataMap->getTransferredSize()->willReturn('123'); // %S
        $dataMap->getCookie('cookie_name')->willReturn('chocolate'); // %{cookie_name}C
        $dataMap->getEnv('env_name')->willReturn('php'); // %{env_name}e
        $dataMap->getRequestHeader('X-Request-Header')->willReturn('request'); // %{X-Request-Header}i
        $dataMap->getResponseHeader('X-Response-Header')->willReturn('response'); // %{X-Response-Header}o
        $dataMap->getPort('local')->willReturn('9999'); // %{local}p
        $dataMap->getRequestTime('end:sec')->willReturn('[1234567890]'); // %{end:sec}t
        $dataMap->getRequestDuration('us')->willReturn('22'); // %{us}T

        $format = '%a %A %B %b %D %f %h %H %m %p %q %r %s %t %T %u %U %v %V %I %O %S'
            . ' %{cookie_name}C %{env_name}e %{X-Request-Header}i %{X-Response-Header}o'
            . ' %{local}p %{end:sec}t %{us}T';
        $expected = [
            '127.0.0.10',
            '127.0.0.1',
            '1234',
            '1234',
            '4321',
            __FILE__,
            $hostname,
            'HTTP/1.1',
            'POST',
            '9000',
            '?foo=bar',
            'POST /path?foo=bar HTTP/1.1',
            '202',
            '[1234567890]',
            '22',
            'expressive',
            '/path',
            'expressive.local',
            'expressive.local',
            '78',
            '89',
            '123',
            'chocolate',
            'php',
            'request',
            'response',
            '9999',
            '[1234567890]',
            '22',
        ];
        $expected = implode(' ', $expected);

        $formatter = new AccessLogFormatter($format);

        $message = $formatter->format($dataMap->reveal());

        $this->assertEquals($expected, $message);
    }
}
