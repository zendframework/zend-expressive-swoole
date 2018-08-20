# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 0.1.2 - TBD

### Added

- [#11](https://github.com/zendframework/zend-expressive-swoole/pull/11) adds the following console actions and options when starting the
  server via `public/index.php`:
  - `start` will start the server; it may be omitted, as this is the default action.
  - `stop` will stop the server
  - `--dameonize|-d` tells the server to daemonize itself when `start` is called.
  - `--num_workers|n` tells the server how many workers to spawn when starting (defaults to 1).

### Changed

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
