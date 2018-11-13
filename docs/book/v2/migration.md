# Migration

This document covers changes between version 1 and version 2, and how you may
update your code to adapt to them.

## Coroutine support

In version 1, to enable Swoole's coroutine support, you were expected to pass a
boolean true value to the
`zend-expressive-swoole.swoole-http-server.options.enable_coroutine` flag.

That flag now controls specifically the HTTP server coroutine support, and
defaults to `true`. To set system-wide coroutine support, toggle the
`zend-expressive-swoole.enable_coroutine` flag, which defaults to boolean false:

```php
return [
    'zend-expressive-swoole' => [
        'enable_coroutine' => false, // system-wide support
        'swoole-http-server' => [
            'options' => [
                'enable_coroutine' => true, // HTTP server coroutine support
            ],
        ],
    ]
];
```

## ServerFactory

Version 2 refactors the architecture slightly to allow providing the HTTP server
as a service, which allows us to [enable async task workers](async-tasks.md).

The primary changes to enable this are:

- `Zend\Expressive\Swoole\ServerFactory` and its associated service was removed.
- `Zend\Expressive\Swoole\ServerFactoryFactory` was removed.
- `Zend\Expressive\Swoole\HttpServerFactory` was created.
- The service `Swoole\Http\Server` was added, pointing to
  `Zend\Expressive\Swoole\HttpServerFactory`.
- The constructor for `Zend\Expressive\Swoole\SwooleRequestHandlerRunner` was
  modified. Previously, the fifth argument was typehinted against the former
  `ServerFactory`; it now typehints against `Swoole\Http\Server`. The factory
  for this class was modified to pass the correct service.

These changes should only affect users who were providing service substitutions
or extending the affected classes.
