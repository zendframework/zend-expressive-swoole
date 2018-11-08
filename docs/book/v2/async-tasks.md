# Triggering Async Tasks in Swoole HTTP Server

If a certain request triggers a workload that perhaps takes a long time to process you'd typically offload this
 work to some kind of message queue perhaps.

The Swoole server process provides the facility to run and communicate with task worker processes using its
 `on('Task')` event handler potentially saving the need for external message queues and additional worker processes.

### Configuring the Server Process

In order to take advantage of this feature within a Zend Expressive application running in the Swoole environment, you
will first need to configure the server to start up task workers. In your local configuration for the server, you'll
 need to add `task_worker_num` 

```php
'zend-expressive-swoole' => [
    'swoole-http-server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'options' => [
            'worker_num' => 4, // The number of HTTP Server Workers
            'task_worker_num' => 4, // The number of Task Workers
        ],
    ],
];
```

### Handling Task Events and Doing the Work

The Swoole server will now require that 2 new event callbacks are non null. These are the `onTask` and `onFinish`
events. Without these setup, the server will refuse to start.

In order to simplify this example, the work to be done, and the event callbacks will be concise functions designed to
illustrate the logic flow:

```php
use Swoole\Http\Server as HttpServer;

$actualWork = function () {
    sleep(5);
    return 'This will be available in the onTask callback';
});

$serverOnTaskCallback = function (HttpServer $server, int $taskId, int $sourceWorkerId, $dataForWorker) use ($actualWork) {
    // Do the work, syncronously
    // $dataForWorker can be any value except for a resource, it is received by triggering a task from the
    // Swoole server process like this: $server->task($data)
    $result = $actualWork();
    return 'Actual Work is Done';
});

$serverOnFinishCallback = function (HttpServer $server, int $taskId, $userData) {
    // Perhaps log completion of the work.
    // $userData === 'Actual Work is Done';
};
```

### Providing Task Event Handlers to the HTTP Server Instance

Now the work is defined, we need to provide these callbacks to the HTTP server. This is best achieved by utilising a
delegator factory to decorate the the server instance at the point of its construction.

In Zend Expressive Swoole, the Http server factory is aliased to Swoole's FQCN, `Swoole\Http\Server`, so a delegator
factory might look something like this:

```php
<?php
// src/App/SwooleHttpDelegatorFactory.php
namespace App;

use Psr\Container\ContainerInterface;
use Swoole\Http\Server as SwooleServer;

class SwooleHttpDelegatorFactory
{
    public function __invoke(ContainerInterface $container, $serviceName, callable $callback) : SwooleServer
     {
        $server = $callback();
        // We're pretending that the callbacks exist in this scope.
        // Typically, you might retrieve a service from the container and inject the server instance into that service
        // in order to configure your tasks, or use the service in a callback directly here.
        $server->on('Task', $serverOnTaskCallback);
        $server->on('Finish', $serverOnFinishCallback);
        return $server;
    }
}
```

You would need to register your delegator factory with the dependency injection container as well. In Zend ServiceManager
this is accomplished in your dependencies configuration in the following way:

```php
'dependencies' => [
    'delegators' => [
        \Swoole\Http\Server::class => [
            \App\SwooleHttpDelegatorFactory::class,
        ],
    ],
],
```

### Triggering Tasks as Part of an HTTP Request

Finally, it is likely that tasks will be triggered by an HTTP request that is travelling through a pipeline, therefore
you will need to create a middleware that triggers the task worker in the Swoole HTTP Server.

The Swoole server provides the `$server->task($data)` method to accomplish this. The `$data` parameter can be any value
except for a resource.

```php
class TaskTriggeringMiddleware implements MiddlewareInterface
{
    private $server;
    
    public function __construct(HttpServer $server)
    {
        $this->server = $server;
    }
    
    public function process(Request $request, Handler $handler) : Response
    {
        // $taskIdentifier is a monotonically incrementing integer used to identify each task. This number is assigned
        // by the Swoole server process.
        $taskIdentifier = $this->server->task('Do Something');
        // The method is asyncronous so execution continues immediately
        return $handler->handle($request);
    }
}
```
