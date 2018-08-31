# Logging

Web servers typically log request details, so that you can perform tasks such as
analytics, identification of invalid requests, and more.

Out-of-the-box, Swoole does not do this. As such, we provide these capabilities
with this integration.

We log a number of items:

- When the web server starts, indicating the host and port on which it is running.
- When workers start, including the working directory and worker ID.
- When the web server stops.
- When the web server reloads workers.
- Each request (more on this below)

By default, logging is performed to STDOUT, using an internal logger. However,
you can use any [PSR-3 compliant logger](https://www.php-fig.org/psr/psr-3/) to
log application details. We emit logs detailing server operations using the
priority `Psr\Log\LogLevel::NOTICE` (unless detailing an error, such as
inability to reload)), while `Psr\Log\LogLevel::INFO` and `Psr\Log\LogLevel::ERROR`
are used to log requests (errors are used for response statuses greater than or
equal to 400).

## Access Logs

Technically, the `SwooleRequestHandlerRunner` doesn't use PSR-3 loggers
directly, but, rather, instances of `Zend\Expressive\Swoole\Log\AccessLogInterface`.
This package-specific interface extends the PSR-3 interface to add two methods:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Zend\Expressive\Swoole\StaticResourceHandler\StaticResourceResponse;

interface AccessLogInterface extends LoggerInterface
{
    public function logAccessForStaticResource(
        Request $request,
        StaticResourceResponse $response
    ) : void;

    public function logAccessForPsr7Resource(
        Request $request,
        ResponseInterface $response
    ) : void;
}
```

To allow usage of a standard PSR-3 logger, we also provide a decorator,
`Zend\Expressive\Swoole\Log\Psr3AccessLogDecorator`, which decorates the PSR-3
logger and provides a standard implementation for the two methods listed above.
If you have defined a PSR-3 `LoggerInterface` service in your application, it
will be used automatically.

### Formatting logs

The Apache web server has long provided flexible and robust logging
capabilities, and its formats are used across a variety of web servers and
logging platforms. As such, we have chosen to use its formats for our standard
implementation. However, we allow you to plug in your own system as needed.

You can refer to the [Apache mod_log_config documentation](http://httpd.apache.org/docs/current/mod/mod_log_config.html)
in order to understand the available placeholders available for format strings.

Formatting is provided to the `Psr3AccessLogDecorator` via instances of the
interface `Zend\Expressive\Swoole\Log\AccessLogFormatterInterface`:

```php
interface AccessLogFormatterInterface
{
    public function format(AccessLogDataMap $map) : string;
}
```

`AccessLogDataMap` is a class used internally by the `Psr3AccessLogDecorator` in
order to map Apache log placeholders to request/response values.

Our default `AccessLogFormatterInterface` implementation, `AccessLogFormatter`,
provides constants referencing the most common formats, but also allows you to
use arbitrary log formats that use the standard Apache placeholders. The formats
we include by default are:

- `AccessLogFormatter::FORMAT_COMMON`: Apache common log format: `%h %l %u %t "%r" %>s %b`
- `AccessLogFormatter::FORMAT_COMMON_VHOST`: Apache common log format + vhost: `%v %h %l %u %t "%r" %>s %b`
- `AccessLogFormatter::FORMAT_COMBINED`: Apache combined log format: `%h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i"`
- `AccessLogFormatter::FORMAT_REFERER`: `%{Referer}i -> %U`
- `AccessLogFormatter::FORMAT_AGENT`: `%{User-Agent}i`
- `AccessLogFormatter::FORMAT_VHOST`: Alternative Apache vhost format: '%v %l %u %t "%r" %>s %b';
- `AccessLogFormatter::FORMAT_COMMON_DEBIAN`: Debian variant of common log format: `%h %l %u %t “%r” %>s %O`;
- `AccessLogFormatter::FORMAT_COMBINED_DEBIAN`: Debian variant of combined log format: `%h %l %u %t “%r” %>s %O “%{Referer}i” “%{User-Agent}i”`;
- `AccessLogFormatter::FORMAT_VHOST_COMBINED_DEBIAN`: Debian variant of combined log format + vhost: `%v:%p %h %l %u %t “%r” %>s %O “%{Referer}i” “%{User-Agent}i"`;

## Configuring a logger

You may subsitute your own logger implementation into the Swoole request handler
runner.

### Manual usage

If you are manually instantiating a `Zend\Expressive\Swoole\SwooleRequestHandlerRunner`
instance, you may provide it as the seventh argument to the constructor:

```php
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;

$runner = new SwooleRequestHandlerRunner(
    $application,
    $serverRequestFactory,
    $serverRequestErrorResponseGenerator,
    $pidManager,
    $serverFactory,
    $staticResourceHandler,
    $logger // <-- AccessLoggerInterface instance
);
```

### Container usage

If you are using a [PSR-11](https://www.php-fig.org/psr/psr-11/) container, the
`SwooleRequestHandlerRunnerFactory` will retrieve a log instance using the
`Zend\Expressive\Swoole\Log\AccessLogInterface` service.

You have two options for substituting your own logger from there.

First, you can create your own factory that produces an `AccessLogInterface`
instance, and map it to the service. This is the best route if you want to write
your own implementation, or want to use a different PSR-3 logger service.

If you are okay with re-using your existing PSR-3 logger, the provided
`Zend\Expressive\Swoole\Log\AccessLogFactory` will use the
`Psr\Log\LoggerInterface` service to create a `Psr3AccessLogDecorator` instance.

This factory also allows you to specify a custom `AccessLogFormatterInterface`
instance if you want. It will look up a service by the fully-qualified interface
name, and use it if present. Otherwise, it creates an `AccessLogFormatter`
instance for you.

The factory will also look at the following configuration values:

```php
'zend-expressive-swoole' => [
    'swoole-http-server' => [
        'logger' => [
            'format' => string, // one of the AccessLogFormatter::FORMAT_*
                                // constants, or a custom format string
            'use-hostname-lookups' => bool, // Set to true to enable hostname lookups
        ],
    ],
],
```
