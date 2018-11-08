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
        'enable_coroutine' => true, // system-wide support
        'swoole-http-server' => [
            'options' => [
                'enable_coroutine' => true, // HTTP server coroutine support
            ],
        ],
    ]
];
```
