# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 0.1.2 - TBD

### Added

- [#20](https://github.com/zendframework/zend-expressive-swoole/pull/20) adds a new interface, `Zend\Expressive\Swoole\StaticResourceHandlerInterface`,
  and default implementation `Zend\Expressive\Swoole\StaticResourceHandler`,
  used to determine if a request is for a static file, and then to serve it; the
  `SwooleRequestHandlerRunner` composes an instance now for providing static
  resource serving capabilities.

  The default implementation uses custom middleware to allow providing common
  features such as HTTP client-side caching headers, handling `OPTIONS`
  requests, etc. Full capabilities include:

  - Emitting `405` statuses for unsupported HTTP methods.
  - Handling `OPTIONS` requests.
  - Handling `HEAD` requests.
  - Providing gzip/deflate compression of response content.
  - Selectively emitting `Cache-Control` headers.
  - Selectively emitting `Last-Modified` headers.
  - Selectively emitting `ETag` headers.

  Please see the [static resource documentation](https://docs.zendframework.com/zend-expressive-swoole/static-resources/)
  for more information.

- [#11](https://github.com/zendframework/zend-expressive-swoole/pull/11) and [#18](https://github.com/zendframework/zend-expressive-swoole/pull/18) add the following console actions and options to interact with
  the server via `public/index.php`:
  - `start` will start the server; it may be omitted, as this is the default action.
  - `stop` will stop the server.
  - `reload` reloads all worker processes, but only when the zend-expressive-swoole.swoole-http-server.mode
    configuration value is set to `SWOOLE_PROCESS`.
  - `--dameonize|-d` tells the server to daemonize itself when `start` is called.
  - `--num_workers|n` tells the server how many workers to spawn when starting (defaults to 1).

### Changed

- [#21](https://github.com/zendframework/zend-expressive-swoole/pull/21) renames `RequestHandlerSwooleRunner` (and its related factory) to `SwooleRequestHandlerRunner`.

- [#20](https://github.com/zendframework/zend-expressive-swoole/pull/20) modifies the collaborators and thus constructor arguments
  expected by the `SwooleRequestHandlerRunner`. The constructor now has the
  following signature:

  ```php
  public function __construct(
      Psr\Http\Server\RequestHandlerInterface $handler,
      callable $serverRequestFactory,
      callable $serverRequestErrorResponseGenerator,
      Zend\Expressive\Swoole\PidManager $pidManager,
      Zend\Expressive\Swoole\ServerFactory $serverFactory,
      Zend\Expressive\Swoole\StaticResourceHandlerInterface $staticResourceHandler = null,
      Psr\Logger\LoggerInterface $logger = null
  ) {
  ```

  If you were manually creating an instance, or had provided your own factory,
  you will need to update your code.

- [#11](https://github.com/zendframework/zend-expressive-swoole/pull/11) modifies how the Swoole HTTP server is started and managed, to
  allow for both starting and stopping the server, as well as ensuring that when
  a process dies and is restarted, no errors are emitted on creation of a new
  HTTP server instance.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.1.1 - 2018-08-14

### Added

- [#5](https://github.com/zendframework/zend-expressive-swoole/pull/5) adds the ability to serve static file resources from your
  configured document root. For information on the default capabilities, as well
  as how to configure the functionality, please see
  https://docs.zendframework.com/zend-expressive-swoole/intro/#serving-static-files.

### Changed

- [#9](https://github.com/zendframework/zend-expressive-swoole/pull/9) modifies how the `RequestHandlerSwooleRunner` provides logging
  output.  Previously, it used `printf()` directly. Now it uses a [PSR-3
  logger](https://www.php-fig.org/psr/psr-3/) instance, defaulting to an
  internal implementation that writes to STDOUT. The logger may be provided
  during instantiation, or via the `Psr\Log\LoggerInterface` service.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#7](https://github.com/zendframework/zend-expressive-swoole/pull/7) fixes how cookies are emitted by the Swoole HTTP server. We now
  use the server `cookie()` method to set cookies, ensuring that multiple
  cookies are not squashed into a single `Set-Cookie` header.

## 0.1.0 - 2018-07-10

### Added

- Everything.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
