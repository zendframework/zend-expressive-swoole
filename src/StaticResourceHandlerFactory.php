<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole;

use Psr\Container\ContainerInterface;

/**
 * Create and return a StaticResourceHandler
 *
 * Uses the following configuration in order to configure and serve static
 * resources from the filesystem:
 *
 * <code>
 * 'zend-expressive-swoole' => [
 *     'swoole-http-server' => [
 *         'document-root' => '/path/to/static/files/to/serve', // usu getcwd() . /public/
 *         'type-map' => [
 *             // extension => mimetype pairs of types to cache.
 *             // A default list exists if none is provided.
 *         ],
 *         'etag-type' => 'weak|strong', // ETag algorithm type to use, if any
 *         'directives' => [
 *             // Rules governing which server-side caching headers are emitted.
 *             // Each key must be a valid regular expression, and should match
 *             // typically only file extensions, but potentially full paths.
 *             // When a static resource matches, all associated rules will apply.
 *             'regex' => [
 *                 'cache-control' => [
 *                     // one or more valid Cache-Control directives:
 *                     // - must-revalidate
 *                     // - no-cache
 *                     // - no-store
 *                     // - no-transform
 *                     // - public
 *                     // - private
 *                     // - max-age=\d+
 *                 ],
 *                 'last-modified' => bool, // Emit a Last-Modified header?
 *                 'etag' => bool, // Emit an ETag header?
 *             ],
 *         ],
 *     ],
 * ],
 * </code>
 */
class StaticResourceHandlerFactory
{
    public function __invoke(ContainerInterface $container) : StaticResourceHandler
    {
        $config = $container->get('config')['zend-expressive-swoole']['swoole-http-server']['static-files'] ?? [];

        $docRoot = $config['document-root'] ?? getcwd() . '/public';
        $typeMap = $config['type-map'] ?? StaticResourceHandler::TYPE_MAP_DEFAULT;
        $etagValidationType = $config['etag-type'] ?? StaticResourceHandler::ETAG_VALIDATION_WEAK;

        $directiveList = $config['directives'] ?? [];
        $cacheControlDirectives = [];
        $lastModifiedDirectives = [];
        $etagDirectives = [];

        foreach ($directiveList as $regex => $directives) {
            if (isset($directives['cache-control'])) {
                $cacheControlDirectives[$regex] = $directives['cache-control'];
            }
            if (isset($directives['last-modified'])) {
                $lastModifiedDirectives[] = $regex;
            }
            if (isset($directives['last-modified'])) {
                $etagDirectives[] = $regex;
            }
        }

        return new StaticResourceHandler(
            $docRoot,
            $typeMap,
            $cacheControlDirectives,
            $lastModifiedDirectives,
            $etagDirectives,
            $etagValidationType
        );
    }
}
