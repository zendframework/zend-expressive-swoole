# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.0.1 - TBD

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0 - 2018-10-02

### Added

- [#38](https://github.com/zendframework/zend-expressive-swoole/pull/38) adds documentation covering potential issues when using a long-running
  server such as Swoole, as well as how to avoid them.

- [#38](https://github.com/zendframework/zend-expressive-swoole/pull/38) adds documentation covering how to use Monolog as a PSR-3 logger for the
  Swoole server.

- [#38](https://github.com/zendframework/zend-expressive-swoole/pull/38) adds a default value of 1024 for the `max_conn` Swoole HTTP server option.
  By default, Swoole uses the value of `ulimit -n` on the system; however, in
  containers and virtualized environments, this value often reports far higher
  than the host system can allow, which can lead to resource problems and
  termination of the server. Setting a default ensures the component can work
  out-of-the-box for most situations. Users should consult their host machine
  specifications and set an appropriate value in production.

### Changed

- [#38](https://github.com/zendframework/zend-expressive-swoole/pull/38) versions the documentation, moving all URLS below the `/v1/` subpath.
  Redirects from the original pages to the new ones were also added.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.2.4 - 2018-10-02

### Added

- [#37](https://github.com/zendframework/zend-expressive-swoole/pull/37) adds support for zendframework/zend-diactoros 2.0.0. You may use either
  a 1.Y or 2.Y version of that library with Expressive applications.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#36](https://github.com/zendframework/zend-expressive-swoole/pull/36) fixes the call to `emitMarshalServerRequestException()` to ensure the
  request is passed to it.

## 0.2.3 - 2018-09-27

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#35](https://github.com/zendframework/zend-expressive-swoole/pull/35) fixes logging when unable to marshal a server request.

## 0.2.2 - 2018-09-05

### Added

- [#28](https://github.com/zendframework/zend-expressive-swoole/pull/28) adds a new option, `zend-expressive-swoole.swoole-http-server.options.enable_coroutine`.
  The option is only relevant for Swoole 4.1 and up. When enabled, this option
  will turn on coroutine support, which essentially wraps most blocking I/O
  operations (including PDO, Mysqli, Redis, SOAP, `stream_socket_client`,
  `fsockopen`, and `file_get_contents` with URIs) into coroutines, allowing
  workers to handle additional requests while waiting for the operations to
  complete.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.2.1 - 2018-09-04

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#30](https://github.com/zendframework/zend-expressive-swoole/pull/30) fixes how the `Content-Length` header is passed to the Swoole response, ensuring we cast the value to a string.

## 0.2.0 - 2018-08-30

### Added

- [#26](https://github.com/zendframework/zend-expressive-swoole/pull/26) adds comprehensive access logging capabilities via a new subnamespace,
  `Zend\Expressive\Swoole\Log`. Capabilities include support (most) of the
  Apache log format placeholders (as well as the standard formats used by Apache
  and Debian), and the ability to provide your own formatting mechanisms. Please
  see the [logging documentation](https://docs.zendframework.com/zend-expressive-swoole/logging/)
  for more information.

- [#20](https://github.com/zendframework/zend-expressive-swoole/pull/20) adds a new interface, `Zend\Expressive\Swoole\StaticResourceHandlerInterface`,
  and default implementation `Zend\Expressive\Swoole\StaticResourceHandler`,
  used to determine if a request is for a static file, and then to serve it; the
  `SwooleRequestHandlerRunner` composes an instance now for providing static
  resource serving capabilities.

  The default implementation uses custom middleware to allow providing common
  features such as HTTP client-side caching headers, handling `OPTIONS`
  requests, etc. Full capabilities include:

  - Filtering by allowed extensions.
  - Emitting `405` statuses for unsupported HTTP methods.
  - Handling `OPTIONS` requests.
  - Handling `HEAD` requests.
  - Providing gzip/deflate compression of response content.
  - Selectively emitting `Cache-Control` headers.
  - Selectively emitting `Last-Modified` headers.
  - Selectively emitting `ETag` headers.

  Please see the [static resource documentation](https://docs.zendframework.com/zend-expressive-swoole/static-resources/)
  for more information.

- [#11](https://github.com/zendframework/zend-expressive-swoole/pull/11), [#18](https://github.com/zendframework/zend-expressive-swoole/pull/18), and [#22](https://github.com/zendframework/zend-expressive-swoole/pull/22) add the following console actions and options to
  interact with the server via `public/index.php`:
  - `start` will start the server; it may be omitted, as this is the default action.
    - `--dameonize|-d` tells the server to daemonize itself when `start` is called.
    - `--num_workers|w` tells the server how many workers to spawn when starting (defaults to 4).
  - `stop` will stop the server.
  - `reload` reloads all worker processes, but only when the zend-expressive-swoole.swoole-http-server.mode
    configuration value is set to `SWOOLE_PROCESS`.

### Changed

- [#21](https://github.com/zendframework/zend-expressive-swoole/pull/21) renames `RequestHandlerSwooleRunner` (and its related factory) to `SwooleRequestHandlerRunner`.

- [#20](https://github.com/zendframework/zend-expressive-swoole/pull/20) and [#26](https://github.com/zendframework/zend-expressive-swoole/pull/26) modify the collaborators and thus constructor arguments
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
      Zend\Expressive\Swoole\Log\AccessLogInterface $logger = null
  ) {
  ```

  If you were manually creating an instance, or had provided your own factory,
  you will need to update your code.

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
