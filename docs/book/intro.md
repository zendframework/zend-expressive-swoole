# Swoole

[Swoole](https://www.swoole.co.uk/) is a PECL extension to develop asynchronous
applications in PHP. It enables PHP developers to write high-performance,
scalable, concurrent TCP, UDP, Unix socket, HTTP, Websocket services without
too much knowledge about non-blocking I/O programming and low-level Linux
kernel.

## Install swoole

You can install the *Swoole* extensions on Linux or Mac environments using the
following commands:

```bash
pecl install swoole
```

The Swoole PECL extension is available [here](https://pecl.php.net/package/swoole).

## Swoole with Expressive

Zend-expressive-swoole enables an Expressive application to be executed with
the [Swoole](https://www.swoole.co.uk/) extension. This means you can run the
application from the command line, **without the usage of a web server**.

You can run the application using the following command:

```php
php public/index.php
```

This command will executes Swoole at `localhost` on port `8080`. You can change
the host address and the port using a configuration file, as follows:

```php
// config/autoload/swoole.local.php
return [
    'swoole_http_server' => [
        'host' => '192.168.0.1',
        'port' => 9501
    ]
];
```

You can also configure the `swoole_http_server` using an `options` key to
specify all the Swoole settings. For instance, here is reported the
configuration of a HTTP server using **SSL**:

```php
// config/autoload/swoole.local.php
return [
    'swoole_http_server' => [
        'host' => '192.168.0.1',
        'port' => 9501,
        'mode' => SWOOLE_BASE,
        'protocol' => SWOOLE_SOCK_TCP | SWOOLE_SSL,
        'options' => [
            'ssl_cert_file' => 'path/to/ssl.crt',
            'ssl_key_file' => 'path/to/ssl.key'
        ]
    ]
];
```
