# How it works

When you run an Expressive application using Swoole you will execute PHP from
the command line interface, **without the usage of a web server**.

This sounds a bit strange in PHP and it's more familiar to [Node.js](https://nodejs.org)
developers. We can say that Swoole enables PHP to be executed as Node.js.

The HTTP server of Swoole is a PHP class that offers some callbacks on events,
using the `on(string $name, callable $action)` function.

The request handler implemented in `zend-expressive-swoole` is a runner that
enables the execution of an Expressive application inside the `on('request')`
event of `swoole_http_server`. This runner is implemented in the
`Zend\Expressive\Swoole\RequestHandlerSwooleRunner` class.

Here is reported the core idea:

```php
public function run() : void
{
    $this->swooleHttpServer->on('start', function ($server) {
        printf("Swoole is running at %s:%s\n", $server->host, $server->port);
    });

    $this->swooleHttpServer->on('request', function ($request, $response) {
        printf(
            "%s - %s - %s %s\n",
            date('Y-m-d H:i:sO', time()),
            $request->server['remote_addr'],
            $request->server['request_method'],
            $request->server['request_uri']
        );
        $emit = new SwooleEmitter($response);
        try {
            $psr7Request = ($this->serverRequestFactory)($request);
        } catch (Throwable $e) {
            // Error in generating the request
            $this->emitMarshalServerRequestException($emit, $e);
            return;
        }
        $emit->emit($this->handler->handle($psr7Request));
    });
    $this->swooleHttpServer->start();
}
```

We implemented a translator from *swoole_http_request* (`$request`) to [PSR-7](https://www.php-fig.org/psr/psr-7/)
request (`$psr7Request`) using `Zend\Expressive\Swoole\ServerRequestSwooleFactory`.

We also implemented a specific emitter for Swoole that converts a PSR-7 response
in *swoole_http_response*. This emitter is implemented in `Zend\Expressive\Swoole\SwooleEmitter`.

When you run an Expressive application using `zend-expressive-swoole`, you will
notice a bunch of PHP processes running. By default, Swoole executes 4 *worker*
processes, 1 *manager* process and 1 *master* process, for a total of 6 PHP
processes.

![Swoole processes](images/diagram_swoole.png)

The advantages of this architecture are many: it's very light and simple, just
PHP processes running; it offers a service layer that is able to restart a
worker automatically, if it's not responding; it allows to execute multiple HTTP
requests in parallel; it's built for scaling.

## Performance

We did a benchmark running the default [zend-expressive-skeleton](https://github.com/zendframework/zend-expressive-skeleton)
application with Swoole 2.1.1, nginx 1.12.1, and Apache 2.4.27 (with mod_php)
using PHP 7.2.3.

The results shown that **Expressive with Swoole runs 4x faster than nginx or
Apache**.

This impressive result comes mainly for to the shared memory approach of
Swoole. Unlike traditional apache/php-fpm stuff, the memory allocated in Swoole
will not be freed after a request.
