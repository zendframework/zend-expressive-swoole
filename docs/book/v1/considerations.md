# Considerations when using Swoole

Because Swoole uses an event loop, and because it is able to load your
application exactly once, you must take several precautions when using it to
serve your application.

## Long-running processes 

When using the Swoole HTTP server, your application runs within an [event
loop](https://en.wikipedia.org/wiki/Event_loop). One benefit of this is that you
can then [_defer_](https://www.swoole.co.uk/docs/modules/swoole-event-loop#public-static-void-swoole-event-defer-mixed-callback)
execution of code until the next tick of the loop. This can be used to delay
long-running code from executing until after a response has been sent to the
client, which can obviate the need for tools such as message queues.

The problem, however, is that when a worker _does_ begin to handle the deferred
functionality, it will run as long as needed until the work is done. This then
means that the worker is _blocked_ from handling new requests until that work is
done.

If you have enough workers, or the number of such long-running processes if few
and far-between, this may not be an issue for you. However, it is a commonly
documented issue in other similar systems such as Node.js. **The solution in these
cases is the same as for general PHP applications: add a message queue to your
systems infrastructure, and delegate such work to the message queue instead.**

## Stateless services

The typical PHP model is that the engine is fired up, runs your code, and then
tears down again, _for every single request_. As such, PHP is said to have a
"shared nothing architecture". This is a tremendous boon to developers, as they
can ignore things found in lower level languages, such as garbage cleanup,
memory management, and more.

This model also comes with a cost: every single request requires bootstrapping
your application. Benchmarks we have performed show that bootstrapping is often
the most expensive operation in applications, often accounting for 25-50% of
total resource usage and execution time.

One reason technologies such as Swoole can provide a performance boost is due to
the fact that they can bootstrap your application exactly once, often during
startup. This alone can account for the performance boost of many applications.

However, it has a price: you now need to consider what changes may happen inside
the various classes in your dependency injection container, and the impact those
changes may have on later requests, or even other requests happening
concurrently.

As one example: [zend-expressive-template](https://docs.zendframework.com/zend-expressive/v3/features/template/intro/)
provides an interface, `TemplateRendererInterface`, that allows you to render a
template. That interface also allows you to provide template paths, and default
parameters to pass to every template, and these methods are often invoked within
factories or delegators in order to configure the renderer implementation.
However, we have [also documented using `addDefaultParam()` for passing values
discovered in the request to later handlers](https://docs.zendframework.com/zend-expressive/v3/cookbook/access-common-data-in-templates/).
This practice accumulates _state_ in the renderer that can cause problems later:

- Flash messages discovered in one request might then be pushed to templates
  renderered in subsequent requests &mdash; when they are no longer in scope. 

- User details from one request might persist to a template rendered for an
  unauthenticated user in another request, exposing information.

These are clearly problematic behaviors!

As such, you must guard against state in services you provide in your dependency
injection container, as any state changes have ramifications for other requests.
Write services to be stateless, and/or mark state-changing methods as
`@internal` to prevent users from calling them in non-bootstrap code.

If the services are provided by a third party, you have a few options:

- Decorating an existing service that implements an interface to make it
  stateless.

- Extending a service to make state-changing methods no-ops.

- Injecting factories that produce the stateful services, instead of the service
  itself.

We'll look at each in detail.

### Decoration

If a service implements an interface, you can decorate the service to make it
stateless. Well-written interfaces will be stateless by design, and not provide
methods meant to internally change state. In these situations, you can create a
proxy class that decorates the original service:

```php
class ProxyService implements OriginalInterface
{
    /** @var OriginalInterface */
    private $proxy;

    public function __construct(OriginalInterface $proxy)
    {
        $this->proxy = $proxy;
    }

    public function someMethodDefinedInInterface(string $argument) : Result
    {
        return $this->proxy->someMethodDefinedInInterface($argument);
    }
}
```

You would then:

- Map the factory for the original service to the implementation name.
- Create a factory that consumes the original service, and produces the proxy.
- Map the interface name to the factory that creates the proxy.

```php
// in config/autoload/dependencies.global.php:

return [
    'dependencies' => [
        'factories' => [
            OriginalImplementation::class => OriginalImplementationFactory::class,
            OriginalInterface::class => ProxyServiceFactory::class,
        ],
    ],
];
```

If you were writing to the interface, and not the implementation, you can now
guarantee that any non-interface methods that changed state can now no longer be
called.

If the interface itself defines methods that modify state, we recommend writing
a proxy that implements those methods as no-ops and/or that raises exceptions
when those methods are invoked. (The latter approach ensures that you discover
quickly when code is exercising those methods.) In each case, you would then use
a [delegator factory](https://docs.zendframework.com/zend-expressive/v3/features/container/delegator-factories/),
to decorate the original instance in the proxy class:

```php
function (ContainerInterface $container, string $name, callable $callback)
{
    return new ProxyService($callback());
}
```

(You can also use the delegator factory approach with the previous proxy service
example.)

### Extension

When a service does not implement an interface, but exposes methods that change
internal state, you can extend the original class to make the methods that
change state into no-ops, or have them raise exceptions. (The latter approach
ensures that you discover quickly when code is exercising those methods.)

As an example, let's say you have a class `DataMapper` that defines a method
`setTable()` in it, and that method would change the database table the mapper
would query. This is a potentially bad situation!

We could extend the class as follows:

```php
class StatelessDataMapper extends DataMapper
{
    public function setTable(string $table) : void
    {
        throw new \DomainException(sprintf(
            '%s should not be called in production code!',
            __METHOD__
        ));
    }
}
```

In your factory that creates an instance of `DataMapper`, have it instead return
a `StatelessDataMapper` instance, and you're now safe.

### Factories

Another approach is to modify your consuming code to accept a _factory_ that
will produce the service you'll consume, instead of the service itself. This
approach ensures that the service is created only when needed, mitigating any
state change issues.

As an example, consider the following middleware that currently consumes a
template renderer:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class SomeHandler implements RequestHandlerInterface
{
    /** @var TemplateRendererInterface */
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return new HtmlResponse($this->renderer->render(
            'app::some-handler',
            []
        ));
    }
}
```

What we will do is modify it to accept a _callable_ to the constructor. We will
then call that factory _just before_ we need the renderer; we _will not_ store
the result in the handler, as we want to ensure we have a new instance each
time.

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class SomeHandler implements RequestHandlerInterface
{
    /** @var callable */
    private $rendererFactory;

    public function __construct(callable $rendererFactory)
    {
        $this->rendererFactory = $rendererFactory;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        /** @var TemplateRendererInterface $renderer */
        $renderer = ($this->rendererFactory)();
        return new HtmlResponse($renderer->render(
            'app::some-handler',
            []
        ));
    }
}
```

From here, we create a factory for our dependency injection container that will
return the factory we use here. As an example, if we are using the [zend-view
integration](https://docs.zendframework.com/zend-expressive/v3/features/template/zend-view/),
we might do the following:

```php

use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\ZendView\ZendViewRendererFactory;

class ZendViewRendererFactoryFactory
{
    public function __invoke(ContainerInterface $container) : callable
    {
        $factory = new ZendViewRendererFactory();
        return function () use ($container, $factory) : TemplateRendererInterface {
            return $factory($container);
        };
    }
}
```

If we mapped this to the "service" `Zend\Expressive\Template\TemplateRendererInterfaceFactory`,
our factory for the `SomeHandler` class would then look like:

```php
use Zend\Expressive\Template\TemplateRendererInterfaceFactory;

function (ContainerInterface $container) : SomeHandler
{
    return new SomeHandler(
        $container->get(TemplateRendererInterfaceFactory::class)
    );
}
```

This approach ensures we get a new instance with known state at precisely the
moment we wish to execute the functionality. By ensuring we _do not_ store the
instance in any way, we also ensure it is garbage collected when the instance
goes out of scope (i.e., when the method ends).

> ### Handling the template data problem
>
> If we want our services to be stateless, how do we handle problems such as the 
> [documented `addDefaultParam()` issue referenced earlier](https://docs.zendframework.com/zend-expressive/v3/cookbook/access-common-data-in-templates/)?
>
> In this case, the original problem was "how do we get common request data into
> templates?" The solution originally provided was to alter the state of the
> template renderer. Another solution, however, is one we've also documented
> previously: [use server attributes to pass data between middleware](https://docs.zendframework.com/zend-expressive/v3/cookbook/passing-data-between-middleware/).
>
> In this particular case, the middleware documented in the original solution
> could be modified to provide data to a request attribute, instead of altering
> the state of the template renderer. It might then become:
>
> ```php
> namespace App\Middleware;
> 
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Message\ServerRequestInterface;
> use Psr\Http\Server\MiddlewareInterface;
> use Psr\Http\Server\RequestHandlerInterface;
> use Zend\Expressive\Router\RouteResult;
> use Zend\Expressive\Session\Authentication\UserInterface;
> use Zend\Expressive\Session\Flash\FlashMessagesInterface;
> 
> class TemplateDefaultsMiddleware implements MiddlewareInterface
> {
>     public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
>     {
>         $routeResult = $request->getAttribute(RouteResult::class);
>         $flashMessages = $request->getAttribute(FlashMessagesInterface::class);
> 
>         $defaults = [
>             // Inject the current user, or null if there isn't one.
>             // This is named security so it will not interfere with your user admin pages
>             'security' => $request->getAttribute(UserInterface::class),
> 
>             // Inject the currently matched route name.
>             'matchedRouteName' => $routeResult ? $routeResult->getMatchedRouteName() : null,
> 
>             // Inject all flash messages
>             'notifications' => $flashMessages ? $flashMessages->getFlashes() : [],
>         ];
> 
>         return $handler->handle($request->withAttribute(__CLASS__, $defaults));
>     }
> }
> ```
>
> Once that change is made, you would then change your handler to do the
> following:
>
> - Pull that attribute, providing a default `[]` value.
> - Merge the pulled value with any local values when rendering the template.
>
> For example:
>
> ```php
> $defaultParams = $request->getAttribute(TemplateDefaultsMiddleware::class, []);
> return new HtmlResponse($renderer->render(
>     'some::template',
>     array_merge($defaultParams, [
>         // handler-specific parameters here
>     ])
> ));
> ```
>
> This approach, while it requires more work on the part of handler authors,
> ensures that the renderer state does not vary between requests, making it
> safer for usage with Swoole and other long-running processes.
