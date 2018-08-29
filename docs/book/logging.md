# Logging

Web servers typically log request details, so that you can perform tasks such as
analytics, identification of invalid requests, and more.

Out-of-the-box, Swoole does not do this. As such, we provide these capabilities
with this integration.

We log two items:

- When the web server starts, indicating the host and port on which it is running.
- Each request, with the following details:
  - Timestamp of the request
  - Remote address of the client making the request
  - Request method
  - Request URI

By default, logging is performed to STDOUT, using an internal logger. However,
you can use any [PSR-3 compliant logger][https://www.php-fig.org/psr/psr-3/] to
log application details. All logs we emit use `Psr\Log\LogLevel::INFO`.

To substitute your own logger, you have two options.

If you are manually instantiating a `Zend\Expressive\Swoole\SwooleRequestHandlerRunner`
instance, you may provide it as the sixth argument to the constructor:

```php
use Zend\Expressive\Swoole\SwooleRequestHandlerRunner;

$runner = new SwooleRequestHandlerRunner(
    $application,
    $serverRequestFactory,
    $serverRequestErrorResponseGenerator,
    $swooleHttpServer,
    $config,
    $logger // <-- PSR-3 logger instance
);
```

If using the provided factory (`SwooleRequestHandlerRunnerFactory`) &amp; which
is the default when using the functionality with Expressive &amp; you can
provide the logger via the `Psr\Log\LoggerInterface` service.
