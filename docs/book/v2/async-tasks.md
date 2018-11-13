# Triggering Async Tasks in Swoole HTTP Server

Application resources requiring lengthy processing are not uncommon. In order to
prevent these processes from impacting user experience, particularly when the
user does not need to wait for the process to complete, we often delegate these
to a [message queue](https://en.wikipedia.org/wiki/Message_queue).

While message queues are powerful, they also require additional infrastructure
for your application, and can be hard to justify when you have a small number of
heavy processes, or a small number of users.

In order to facilitate async processing, Swoole servers provides task worker
processes, allowing your application to trigger tasks without the need for an
external message queue, and without impacting the server worker processes
&mdash; allowing your application to continue responding to requests while the
task is processed.

## Configuring the Server Process

In order to take advantage of this feature, you will first need to configure the
server to start up task workers. In your local configuration for the server,
you'll need to add `task_worker_num`. The number of workers you configure define
the number of concurrent tasks that can be executed at once. Tasks are queued in
the order that they triggered, meaning that a `task_worker_num` of 1 will offer
no concurrency and tasks will execute one after the other.

```php
'zend-expressive-swoole' => [
    'swoole-http-server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'options' => [
            'worker_num'      => 4, // The number of HTTP Server Workers
            'task_worker_num' => 4, // The number of Task Workers
        ],
    ],
];
```

> ### No CLI option for task_worker_num
>
> Unlike `worker_num`, there is no CLI option for `task_worker_num`. This is
> because enabling the task worker also requires registering a task worker
> with the server. To prevent accidental startup failures due to passing an
> option to specify the number of task workers without having registered a
> task worker, we omitted the CLI option.

## Task Event Handlers

When task workers are enabled, the Swoole server will now **require** that you
register two event callbacks with the server; without them, the server will
refuse to start.

The two events are:

- `onTask`/`task`, which will define the code for handling tasks.
- `onFinish`/`finish`, which will execute when a task has completed.

### Registering the Handlers

The signature for the `onTask` event handler is:

```php
function (
    \Swoole\Http\Server $server,
    int $taskId,
    int $sourceWorkerId,
    $dataForWorker
) : void
```
where:

- `$server` is the main HTTP server process
- `$taskId` is a number that increments each time the server triggers a new task.
- `$sourceWorkerId` is an integer that defines the worker process that is
  executing the workload.
- `$dataForWorker` contains the value passed to the `$server->task()` method
  when initially triggering the task. This value can be any PHP value, with the
  exception of a `resource`.

To register the handler with the server, you must call it's `on()` method,
**before** the server has been started in either of the following ways:

```php
// Using the `onTask` method:
$server->onTask($callable);

// Using general event registration (this is more the more accepted form):
$server->on('task', $callable);
```

As previously mentioned, you must also register an event handler for the
`'finish'` event. This event handler has the following signature:

```php
function (
    \Swoole\Http\Server $server,
    int $taskId,
    $userData
) : void
```

The first two parameters are identical to the `onTask` event handler. The
`$userData` parameter will contain the return value of the `onTask` event
handler. 

Registering your callable for the finish event is accomplished like this:

```php
// Using the `onFinish` method:
$server->onFinish($callable);

// Using general event registration (this is more the more accepted form):
$server->on('finish', $callable);
```

> ### There can be only one
>
> There can be **only one** event handler per event type. Subsequent calls to
> `on('<EventName>')` replace the previously registered callable.

> ### Finishing a task
>
> If you do not return anything from your `onTask` event handler, the
> `onFinish` handler **will not be called**. The Swoole documentation recommends
> that the task worker callback manually finish the task in these situations:
>
> ```php
> $server->finish('');
> ```
>
> Even if you do not call the above method, the handler **must** be defined, or
> the server will refuse to start.

## An example task worker

The following example code illustrates a task worker with logging capabilities
that uses a message notifier to process data:

```php
// In src/App/TaskWorker.php:

namespace App;

use Psr\EventDispatcher\MessageInterface;
use Psr\EventDispatcher\MessageNotifierInterface;
use Psr\Log\LoggerInterface;
use Throwable;

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
            $this->logger->error('Invalid data type provided to task worker: {type}', [
                'type' => is_object($data) ? get_class($data) : gettype($data)
            ]);
            return;
        }

        $this->logger->notice('Starting work on task {taskId} using data: {data}', [
            'taskId' => $taskId,
            'data' => json_encode($data),
        ]);

        try {
            $this->notifier->notify($data);
        } catch (Throwable $e) {
            $this->logger->error('Error processing task {taskId}: {error}', [
                'taskId' => $taskId,
                'error' => $e->getTraceAsString(),
            ]);
        }

        // Notify the server that processing of the task has finished:
        $server->finish('');
    }
}
```

This invokable class needs to be attached to the `$server->on('task')` event
before the server has started. The easiest place to accomplish this is in a
[delegator factory](https://docs.zendframework.com/zend-expressive/v3/features/container/delegator-factories/)
targeting the Swoole HTTP server. First, we'll create the delegator factory:

```php
// In src/App/TaskWorkerDelegator.php:

namespace App;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as HttpServer;

class TaskWorkerDelegator
{
    public function __invoke(ContainerInterface $container, $serviceName, callable $callback) : HttpServer
    {
        $server = $callback();
        $logger = $container->get(LoggerInterface::class);

        $server->on('task', $container->get(TaskWorker::class));
        $server->on('finish', function ($server, $taskId, $data) use ($logger) {
            $logger->notice('Task #{taskId} has finished processing', ['taskId' => $taskId]);
        });

        return $server;
    }
}
```

Next, we'll register it with our container:

```php
// In config/autoload/dependencies.php:

return [
    'dependencies' => [
        'delegators' => [
            \Swoole\Http\Server::class => [
                \App\TaskWorkerDelegator::class,
            ],
        ],
    ],
];
```

With this in place, we can now trigger tasks within our application. In the
scenario outlined above, the task worker expects _messages_; it then _notifies_
listeners of that message so they may respond to it.

## Triggering Tasks in Middleware

Considering that this library provides an application runner for middleware
applications, you will likely trigger tasks from within your middleware or
request handlers. In each case, you will need to compose the Swoole HTTP server
instance as a class dependency, as tasks are triggered via the server via its
`task()` method. The method can accept any value except a resource as an
argument.

In the example below, `ContactMessage` will implement the `MessageInterface`
from the above example. The request handler uses values from the request to
create the `ContactMessage` instance, and then create a task from it. It then
immediately returns a response.

```php
// in src/App/Handler/TaskTriggeringHandler.php:

namespace App\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Server as HttpServer;
use Zend\Expressive\Template\TemplateRendererInterface;

class TaskTriggeringHandler implements RequestHandlerInterface
{
    private $responseFactory;
    private $server;
    private $template;
    
    public function __construct(
        HttpServer $server,
        TemplateRendererInterface $template,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->server          = $server;
        $this->template        = $template;
        $this->responseFactory = $responseFactory;
    }
    
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // Gather data from request
        $data = $request->getParsedBody();

        // A fictonal event describing a contact request:
        $event = new ContactEvent([
            'to'      => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        // task() returns a task identifier, if you want to use it; otherwise,
        // you can ignore the return value.
        $taskIdentifier = $this->server->task($event);

        // The task() method is asynchronous, so execution continues immediately.
        $response = ($this->responseFactory()->createResponse())
            ->withHeader('Content-Type', 'text/html');
        $response->getBody()->write($this->template->render('contact::thank-you', []);
        return $response;
    }
}
```
