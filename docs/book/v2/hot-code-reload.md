# Hot Code Reload

To ease development when using swoole server, hot code reload can be enabled.

With this feature enabled, a swoole worker will monitor included PHP files using inotify, and will restart all workers
if a file is changed, thus mitigating the need to manually restart the server to "view" changes made.

**This feature should only be used in your local development environment, and should not be used in production!**

## Requirements

* `ext-inotify`

This library ships with an [inotify](http://php.net/manual/en/book.inotify.php) based implementation of 
`\Zend\Expressive\Swoole\HotCodeReload\FileWatcherInterface`. In order to use it, the `inotify` extension must be
loaded.

## Configuration

The following demonstrates all currently available configuration options:

```php
// config/autoload/swoole.local.php

return [
    'zend-expressive-swoole' => [
        'hot-code-reload' => [
            // Since 2.3.0: Set to true to enable hot code reload.
            // This is false by default.
            'enable' => true,
            
            // Time in milliseconds between checks to changes in files.
            'interval' => 500,
        ],
    ],
];
```

## Limitations

Only files included by php after `onWorkerStart` will be reloaded.
The reloaded swoole server will not pick up added routes or pipeline middleware, nor changes to the server or
Application instances.

This limitation exists because the hot code reloaded uses the `Swoole\Server::reload` method to signal swoole to reload
php files (see https://www.swoole.co.uk/docs/modules/swoole-server-methods#public-boolean-swoole-server-reload-void).
