<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend\Expressive\Swoole\StaticResourceHandler;
use Zend\Expressive\Swoole\StaticResourceHandlerFactory;

class StaticResourceHandlerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function assertHasMiddlewareOfType(string $type, array $middlewareList)
    {
        $middleware = $this->getMiddlewareByType($type, $middlewareList);
        $this->assertInstanceOf($type, $middleware);
    }

    public function getMiddlewareByType(string $type, array $middlewareList)
    {
        foreach ($middlewareList as $middleware) {
            if ($middleware instanceof $type) {
                return $middleware;
            }
        }
        $this->fail(sprintf(
            'Could not find middleware of type %s',
            $type
        ));
    }

    public function testFactoryConfiguresHandlerBasedOnConfiguration()
    {
        $config = [
            'zend-expressive-swoole' => [
                'swoole-http-server' => [
                    'static-files' => [
                        'document-root' => __DIR__ . '/TestAsset',
                        'type-map' => [
                            'png' => 'image/png',
                            'txt' => 'text/plain',
                        ],
                        'clearstatcache-interval' => 3600,
                        'etag-type' => 'strong',
                        'directives' => [
                            '/\.txt$/' => [
                                'cache-control' => [
                                    'no-cache',
                                    'must-revalidate',
                                ],
                            ],
                            '/\.png$/' => [
                                'cache-control' => [
                                    'public',
                                    'no-transform',
                                ],
                                'last-modified' => true,
                                'etag' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->container->get('config')->willReturn($config);

        $factory = new StaticResourceHandlerFactory();

        $handler = $factory($this->container->reveal());

        $this->assertAttributeSame(
            $config['zend-expressive-swoole']['swoole-http-server']['static-files']['document-root'],
            'docRoot',
            $handler
        );

        $r = new ReflectionProperty($handler, 'middleware');
        $r->setAccessible(true);
        $middleware = $r->getValue($handler);

        $this->assertHasMiddlewareOfType(StaticResourceHandler\ContentTypeFilterMiddleware::class, $middleware);
        $this->assertHasMiddlewareOfType(StaticResourceHandler\MethodNotAllowedMiddleware::class, $middleware);
        $this->assertHasMiddlewareOfType(StaticResourceHandler\OptionsMiddleware::class, $middleware);
        $this->assertHasMiddlewareOfType(StaticResourceHandler\HeadMiddleware::class, $middleware);
        $this->assertHasMiddlewareOfType(StaticResourceHandler\ClearStatCacheMiddleware::class, $middleware);

        $contentTypeFilter = $this->getMiddlewareByType(
            StaticResourceHandler\ContentTypeFilterMiddleware::class,
            $middleware
        );
        $this->assertAttributeSame(
            $config['zend-expressive-swoole']['swoole-http-server']['static-files']['type-map'],
            'typeMap',
            $contentTypeFilter
        );

        $clearStatsCache = $this->getMiddlewareByType(
            StaticResourceHandler\ClearStatCacheMiddleware::class,
            $middleware
        );
        $this->assertAttributeSame(
            $config['zend-expressive-swoole']['swoole-http-server']['static-files']['clearstatcache-interval'],
            'interval',
            $clearStatsCache
        );

        $this->assertHasMiddlewareOfType(StaticResourceHandler\CacheControlMiddleware::class, $middleware);
        $cacheControl = $this->getMiddlewareByType(StaticResourceHandler\CacheControlMiddleware::class, $middleware);
        $this->assertAttributeEquals(
            [
                '/\.txt$/' => [
                    'no-cache',
                    'must-revalidate',
                ],
                '/\.png$/' => [
                    'public',
                    'no-transform',
                ],
            ],
            'cacheControlDirectives',
            $cacheControl
        );

        $this->assertHasMiddlewareOfType(StaticResourceHandler\LastModifiedMiddleware::class, $middleware);
        $lastModified = $this->getMiddlewareByType(StaticResourceHandler\LastModifiedMiddleware::class, $middleware);
        $this->assertAttributeEquals(
            ['/\.png$/'],
            'lastModifiedDirectives',
            $lastModified
        );

        $this->assertHasMiddlewareOfType(StaticResourceHandler\ETagMiddleware::class, $middleware);
        $eTag = $this->getMiddlewareByType(StaticResourceHandler\ETagMiddleware::class, $middleware);
        $this->assertAttributeEquals(
            ['/\.png$/'],
            'etagDirectives',
            $eTag
        );
        $this->assertAttributeEquals(
            StaticResourceHandler\ETagMiddleware::ETAG_VALIDATION_STRONG,
            'etagValidationType',
            $eTag
        );
    }
}
