# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 0.1.1 - TBD

### Added

- [#5](https://github.com/zendframework/zend-expressive-swoole/pull/5) adds the ability to serve static file resources from your
  configured document root. For information on the default capabilities, as well
  as how to configure the functionality, please see
  https://docs.zendframework.com/zend-expressive-swoole/intro/#serving-static-files.

### Changed

- Nothing.

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
