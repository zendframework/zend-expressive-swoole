# Hot Code Reload

> - Since 2.3.0

To ease development against a running Swoole HTTP server, hot code reloading can
be enabled.

With this feature enabled, a Swoole worker will monitor included PHP files using
`inotify`, and will restart all workers if a file is changed, thus mitigating
the need to manually restart the server to test changes.

**This feature should only be used in your local development environment, and
should not be used in production!**

## Requirements

- `ext-inotify`

This library ships with an [inotify](http://php.net/manual/en/book.inotify.php)
based implementation of `Zend\Expressive\Swoole\HotCodeReload\FileWatcherInterface`.
In order to use it, the `inotify` extension must be loaded.

## Configuration

The following demonstrates all currently available configuration options:

```php
// config/autoload/swoole.local.php

return [
    'zend-expressive-swoole' => [
        'hot-code-reload' => [
            // Set to true to enable hot code reload; the default is false.
            'enable' => true,
            
            // Time in milliseconds between checks to changes in files.
            'interval' => 500,
        ],
    ],
];
```

## Logging

When a file is reloaded, a *notice* line will be logged with the message
`Reloading due to file change: {path}`.

The logger used to log these lines is the same used for access logging, which
is described in the [logging section](logging.md#container-usage) of this documentation.

## Limitations

Only files included by PHP after `onWorkerStart` will be reloaded. This means
that Swoole will not reload any of the following:

- New routes
- New pipeline middleware
- The `Application` instance, _or any delegators used to modify it_.
- The Swoole HTTP server itself.

This limitation exists because the hot code reload features use the
`Swoole\Server::reload()` method to notify Swoole to reload
PHP files (see [the Swoole reload() documentation for more details](https://www.swoole.co.uk/docs/modules/swoole-server-methods#public-boolean-swoole-server-reload-void)).
