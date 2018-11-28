# Swoole

[Swoole](https://www.swoole.co.uk/) is a PECL extension for developing
asynchronous applications in PHP. It enables PHP developers to write
high-performance, scalable, concurrent TCP, UDP, Unix socket, HTTP, or Websocket
services without requiring in-depth knowledge about non-blocking I/O programming
or the low-level Linux kernel.

## Install swoole

You can install the Swoole extension on Linux or Mac environments using the
following commands:

```bash
$ pecl install swoole
```

For more information on the extension, [visit its package details on PECL](https://pecl.php.net/package/swoole).

## Install zend-expressive-swoole

To install this package, use [Composer](https://getcomposer.org/):

```bash
$ composer require zendframework/zend-expressive-swoole
```

## Swoole with Expressive

zend-expressive-swoole enables an Expressive application to be executed with
the [Swoole](https://www.swoole.co.uk/) extension. This means you can run the
application from the command line, **without requiring a web server**.

You can run the application using the following command:

```bash
$ ./vendor/bin/zend-expressive-swoole start
```

This command will execute Swoole on `localhost` via port `8080`.

> ### Other commands
>
> To get a list of all available commands, run the command without arguments:
>
> ```bash
> $ ./vendor/bin/zend-expressive-swoole
> ```
>
> If you add the argument `help` before any command name, the tooling will
> provide you with more detailed information on that command.

> ### Expressive skeleton versions prior to 3.1.0
>
> The above will work immediately after installing zend-expressive-swoole if you
> are using a version of [zend-expressive-skeleton](https://github.com/zendframework/zend-expressive-skeleton)
> from 3.1.0 or later.
>
> For applications based on previous versions of the skeleton, you will need to
> create a configuration file such as `config/autoload/zend-expressive-swoole.global.php`
> or `config/autoload/zend-expressive-swoole.local.php` with the following
> contents:
>
> ```php
> <?php
> use Zend\Expressive\Swoole\ConfigProvider;
>
> return (new ConfigProvider())();
> ```

You can change the host address and/or host name as well as the port using a
configuration file, as follows:

```php
// In config/autoload/swoole.local.php:
return [
    'zend-expressive-swoole' => [
        'swoole-http-server' => [
            'host' => '192.168.0.1',
            'port' => 9501,
        ],
    ],
];
```

### Providing additional Swoole configuration

You can also configure the Swoole HTTP server using an `options` key to specify
any accepted Swoole settings. For instance, the following configuration
demonstrates enabling SSL:

```php
// config/autoload/swoole.local.php
return [
    'zend-expressive-swoole' => [
        // Available in Swoole 4.1 and up; enables coroutine support
        // for most I/O operations:
        'enable_coroutine' => true,

        // Configure Swoole HTTP Server:
        'swoole-http-server' => [
            'host' => '192.168.0.1',
            'port' => 9501,
            'mode' => SWOOLE_BASE, // SWOOLE_BASE or SWOOLE_PROCESS;
                                   // SWOOLE_BASE is the default
            'protocol' => SWOOLE_SOCK_TCP | SWOOLE_SSL, // SSL-enable the server
            'options' => [
                // Set the SSL certificate and key paths for SSL support:
                'ssl_cert_file' => 'path/to/ssl.crt',
                'ssl_key_file' => 'path/to/ssl.key',
                // Whether or not the HTTP server should use coroutines;
                // enabled by default, and generally should not be disabled:
                'enable_coroutine' => true,
            ],

            // Since 2.1.0: Set the process name prefix.
            // The master process will be named `{prefix}-master`,
            // worker processes will be named `{prefix}-worker-{id}`,
            // and task worker processes will be named `{prefix}-task-worker-{id}`
            'process-name' => 'your-app',
        ],
    ],
];
```

> ### SSL support
>
> By default, Swoole is not compiled with SSL support. To enable SSL in Swoole, it
> must be configured with the `--enable-openssl` or
> `--with-openssl-dir=/path/to/openssl` option.

### Serving static files

We support serving static files. By default, we serve files with extensions in
the whitelist defined in the constant `Zend\Expressive\Swoole\StaticResourceHandler\ContentTypeFilterMiddleware::DEFAULT_STATIC_EXTS`,
which is derived from a [list of common web MIME types maintained by Mozilla](https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types).
Our static resource capabilities are fairly comprehensive; please see the
[chapter on static resources](static-resources.md) for full details on
configuration.
