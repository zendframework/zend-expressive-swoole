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
pecl install swoole
```

For more information on the extension, [visit its package details on PECL](https://pecl.php.net/package/swoole).

## Swoole with Expressive

zend-expressive-swoole enables an Expressive application to be executed with
the [Swoole](https://www.swoole.co.uk/) extension. This means you can run the
application from the command line, **without requiring a web server**.

You can run the application using the following command:

```php
php public/index.php
```

This command will execute Swoole on `localhost` via port `8080`. You can change
the host address and/or host name as well as the port using a configuration
file, as follows:

```php
// config/autoload/swoole.local.php
return [
    'swoole_http_server' => [
        'host' => '192.168.0.1',
        'port' => 9501
    ]
];
```

You can also configure the Swoole HTTP server using an `options` key to specify
any accepted Swoole settings. For instance, the following configuration
demonstrates enabling SSL:

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
