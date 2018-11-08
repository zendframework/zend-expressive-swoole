# Triggering Async Tasks in Swoole HTTP Server

If a certain request triggers a workload that perhaps takes a long time to process you'd typically offload this
 work to some kind of message queue perhaps.

The Swoole server process provides the facility to run and communicate with task worker processes using its
 `on('Task')` event handler potentially saving the need for external message queues and additional worker processes.

### Configuring the Server Process

In order to take advantage of this feature within a Zend Expressive application running in the Swoole environment, you
will first need to configure the server to start up task workers. In your local configuration for the server, you'll
 need to add `task_worker_num`. The number of workers you configure define the number of concurrent tasks that can be
 executed at once. Tasks are queued in the order that they triggered, meaning that a `task_worker_num` of 1
 will offer no concurrency and tasks will execute one after the other; the size of that buffer is currently
 not known.

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

### Task Event Handlers

The Swoole server will now require that 2 new event callbacks are non null. These are the `onTask` and `onFinish`
events. Without both of these setup, the server will refuse to start.

#### Registering the Handlers

The signature for the `onTask` event handler is this:

```php
use Swoole\Http\Server as HttpServer;
$serverOnTaskCallback = function (HttpServer $server, int $taskId, int $sourceWorkerId, $dataForWorker) {};
```

- `$server` is the main Http Server process
- `$taskId` is a number that increments each time the server triggers a new task.
- `$sourceWorkerId` is an integer that defines the worker process that is executing the workload.
- `$dataForWorker` contains the value passed to the `$server->task()` method when initially triggering the task. This
 value can be anything you define with the exception of a `resource`.

To register the handler with the server, you must call it's `on()` method, **before** the server has been started in the
following way:

```php
$server->on('Task', $callable);
```

There can be **only one** event handler per event type. Subsequent calls to `on('<EventName>')` replace the previously 
registered callable.

As previously mentioned, you must also register an event handler for the `'Finish'` event. This event handler has the
 following signature:

```php
$serverOnFinishCallback = function (HttpServer $server, int $taskId, $userData) {};
```

The first 2 parameters are identical to the `onTask` event handler. The `$userData` parameter will contain the return
value of the `onTask` event handler.

If you do not return anything from your `onTask` event handler, the `onFinish` handler **will not be called**.

Registering your callable for the finish event is accomplished like this:

```php
$server->on('Finish', $callable);
```

## An example task broker

The following example code illustrates dispatching events and performing logging duties as a task handler:

```php
use Psr\EventDispatcher\MessageInterface;
use Psr\EventDispatcher\MessageNotifierInterface;
use Psr\Log\LoggerInterface;

class TaskWorker
{
    private $notifier;
    private $logger;

    public function __construct(LoggerInterface $logger, MessageNotifierInterface $notifier)
    {
        $this->logger = $logger;
        $this->notifier = $notifier;
    }

    public function __invoke($server, $taskId, $fromId, $data)
    {
        if (! $data instanceof MessageInterface) {
            $this->logger->error('Invalid data provided to task worker: {type}', ['type' => is_object($data) ? get_class($data) : gettype($data)]);
            return;
        }
        $this->logger->info('Starting work on task {taskId} using data: {data}', ['taskId' => $taskId, 'data' => json_encode($data)]);
        try {
            $this->notifier->notify($data);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing task {taskId}: {error}', ['taskId' => $taskId, 'error' => $e->getTraceAsString()]);
        }
    }
}
```

This invokable class needs to be attached to the `$server->on('Task')` event before the server has started. The
easiest place to accomplish this is in a delegator factory targeting the Swoole Http Server:

```php
// config/dependencies.php
return [
    'dependencies' => [
        'delegators' => [
            \Swoole\Http\Server::class => [
                TaskWorkerDelegator::class,
            ],
        ],
    ],
];

// TaskWorkerDelegator.php

use Psr\Container\ContainerInterface;
use Swoole\Http\Server as HttpServer;
use Psr\Log\LoggerInterface;

class TaskWorkerDelegator
{
    public function __invoke(ContainerInterface $container, $serviceName, callable $callback) : HttpServer
    {
        $server = $callback();
        $server->on('Task', $container->get(TaskWorker::class));
        
        $logger = $container->get(LoggerInterface::class);
        $server->on('Finish', function ($server, $taskId, $data) use ($logger) {
            $logger->notice('Task #{taskId} has finished processing', ['taskId' => $taskId]);
        });
        return $server;
    }
}
```

### Triggering Tasks in Middleware

Finally, it is likely that tasks will be triggered by an HTTP request that is travelling through a pipeline, therefore
you will need to create a middleware that triggers the task worker in the Swoole HTTP Server.

As previously mentioned, the Swoole server provides the `$server->task($data)` method to accomplish this. The `$data`
 parameter can be any value except for a resource.

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
        // A fictonal event describing the requirement to send an email:
        $event = new EmailEvent([
            'to' => 'you@example.com',
            'subject' => 'Non-blocking you say?',
        ]);
        $taskIdentifier = $this->server->task($event);
        // The method is asyncronous so execution continues immediately
        return $handler->handle($request);
    }
}
```
