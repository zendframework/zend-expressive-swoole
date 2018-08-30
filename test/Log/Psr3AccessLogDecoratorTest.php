<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\Log;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface as Psr7Response;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionProperty;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\Log\AccessLogDataMap;
use Zend\Expressive\Swoole\Log\AccessLogFormatterInterface;
use Zend\Expressive\Swoole\Log\Psr3AccessLogDecorator;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

class Psr3AccessLogDecoratorTest extends TestCase
{
    public function setUp()
    {
        $this->psr3Logger = $this->prophesize(LoggerInterface::class);
        $this->formatter = $this->prophesize(AccessLogFormatterInterface::class);
        $this->request = $this->prophesize(Request::class)->reveal();
        $this->psr7Response = $this->prophesize(Psr7Response::class);
        $this->staticResponse = $this->prophesize(StaticResourceResponse::class);
    }

    public function psr3Methods() : iterable
    {
        $r = new ReflectionClass(LoggerInterface::class);
        foreach ($r->getMethods() as $method) {
            $name = $method->getName();
            yield $name => [$name];
        }
    }

    /**
     * @dataProvider psr3Methods
     */
    public function testProxiesToPsr3Methods(string $method)
    {
        $logger = new Psr3AccessLogDecorator($this->psr3Logger->reveal(), $this->formatter->reveal());
        switch ($method) {
            case 'log':
                $this->psr3Logger
                    ->log(LogLevel::DEBUG, 'message', ['foo' => 'bar'])
                    ->shouldBeCalled();
                $this->assertNull($logger->log(LogLevel::DEBUG, 'message', ['foo' => 'bar']));
                break;
            default:
                $this->psr3Logger
                    ->$method('message', ['foo' => 'bar'])
                    ->shouldBeCalled();
                $this->assertNull($logger->$method('message', ['foo' => 'bar']));
                break;
        }
    }

    public function statusLogMethodValues()
    {
        return [
            '100' => [100, 'info'],
            '200' => [200, 'info'],
            '302' => [302, 'info'],
            '400' => [400, 'error'],
            '500' => [500, 'error'],
        ];
    }

    /**
     * @dataProvider statusLogMethodValues
     */
    public function testLogAccessForStaticResourceFormatsMessageAndPassesItToPsr3Logger(
        int $status,
        string $logMethod
    ) {
        $expected = 'message';
        $request = $this->request;

        $response = $this->staticResponse;
        $response->getStatus()->willReturn($status);

        $this->formatter
            ->format(
                Argument::that(function ($mapper) use ($request, $response) {
                    TestCase::assertInstanceOf(AccessLogDataMap::class, $mapper);
                    TestCase::assertAttributeSame($request, 'request', $mapper);
                    TestCase::assertAttributeSame($response->reveal(), 'staticResource', $mapper);
                    TestCase::assertAttributeSame(false, 'useHostnameLookups', $mapper);
                    return true;
                })
            )
            ->willReturn($expected);

        $this->psr3Logger->$logMethod($expected)->shouldBeCalled();

        $logger = new Psr3AccessLogDecorator($this->psr3Logger->reveal(), $this->formatter->reveal());

        $this->assertNull($logger->logAccessForStaticResource(
            $this->request,
            $response->reveal()
        ));
    }

    /**
     * @dataProvider statusLogMethodValues
     */
    public function testLogAccessForPsr7ResourceFormatsMessageAndPassesItToPsr3Logger(
        int $status,
        string $logMethod
    ) {
        $expected = 'message';
        $request = $this->request;

        $response = $this->psr7Response;
        $response->getStatusCode()->willReturn($status);

        $this->formatter
            ->format(
                Argument::that(function ($mapper) use ($request, $response) {
                    TestCase::assertInstanceOf(AccessLogDataMap::class, $mapper);
                    TestCase::assertAttributeSame($request, 'request', $mapper);
                    TestCase::assertAttributeSame($response->reveal(), 'psrResponse', $mapper);
                    TestCase::assertAttributeSame(false, 'useHostnameLookups', $mapper);
                    return true;
                })
            )
            ->willReturn($expected);

        $this->psr3Logger->$logMethod($expected)->shouldBeCalled();

        $logger = new Psr3AccessLogDecorator($this->psr3Logger->reveal(), $this->formatter->reveal());

        $this->assertNull($logger->logAccessForPsr7Resource(
            $this->request,
            $response->reveal()
        ));
    }
}
