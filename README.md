<div align="center">
    <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="Assegai Logo"></a>
</div>

# Assegai Events

`assegaiphp/events` is a small event emitter package for both AssegaiPHP projects and standalone PHP applications.

It is intentionally framework-light:
- emit named events such as `orders.created`
- emit event objects such as `new OrderCreated(...)`
- register listeners directly with `on(...)` / `once(...)`
- register listener classes with `#[OnEvent(...)]`
- use wildcard listeners such as `orders.*`
- auto-register `#[OnEvent(...)]` listeners in Assegai modules through `EventsModule`
- observe listener failures with failure hooks
- record durable events into an outbox store when in-process delivery is not enough

## Install

```bash
composer require assegaiphp/events
```

For Assegai projects, the CLI shortcut is:

```bash
assegai add events
```

## Assegai usage

Import the events module once, then inject the emitter into your services and declare listeners with `#[OnEvent(...)]`.

```php
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Events\Assegai\AssegaiEventEmitter;
use Assegai\Events\Assegai\EventsModule;
use Assegai\Events\Attributes\OnEvent;

#[Injectable]
final class OrdersService
{
  public function __construct(
    private readonly AssegaiEventEmitter $events,
  )
  {
  }

  public function create(array $order): void
  {
    $this->events->emit('orders.created', $order);
  }
}

#[Injectable]
final class OrderListener
{
  #[OnEvent('orders.created')]
  public function handle(array $payload): void
  {
    // send email, write audit log, update projections...
  }
}

#[Module(
  imports: [EventsModule::class],
  providers: [OrdersService::class, OrderListener::class],
)]
final class AppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
```

By default, the Assegai bridge registers `#[OnEvent(...)]` listeners during application bootstrap. That means events emitted from very early bootstrap code can still be missed, just like the NestJS pattern this package is modeled after.

If you need to delay an early emit until listener registration has completed, inject the readiness watcher and wait for it:

```php
use Assegai\Events\Assegai\EventEmitterReadinessWatcherProvider;

public function __construct(
  private readonly EventEmitterReadinessWatcherProvider $eventsReady,
  private readonly AssegaiEventEmitter $events,
)
{
}

public function boot(): void
{
  $this->eventsReady->waitUntilReady();
  $this->events->emit('orders.created', ['orderId' => 1]);
}
```

## Standalone usage

```php
use Assegai\Events\EventEmitter;

$events = new EventEmitter();

$events->on('orders.created', function (array $payload) {
  // send email, update projections, etc.
});

$events->emit('orders.created', [
  'orderId' => 42,
]);
```

## Event objects

```php
use Assegai\Events\EventEmitter;

final readonly class OrderCreated
{
  public function __construct(public int $orderId)
  {
  }
}

$events = new EventEmitter();

$events->on(OrderCreated::class, function (OrderCreated $event) {
  // handle typed event object
});

$events->emit(new OrderCreated(42));
```

## Attribute-based listeners

```php
use Assegai\Events\Attributes\OnEvent;
use Assegai\Events\EventEmitter;
use Assegai\Events\ReflectiveListenerProvider;

final class OrderListener
{
  #[OnEvent('orders.created')]
  public function onNamedEvent(array $payload): void
  {
    // ...
  }

  #[OnEvent(OrderCreated::class)]
  public function onTypedEvent(OrderCreated $event): void
  {
    // ...
  }
}

$events = new EventEmitter();
$provider = new ReflectiveListenerProvider($events);
$provider->register(new OrderListener());
```

Registering the same listener instance more than once through the reflective provider is safe. Duplicate method registrations for the same object instance are ignored.

## Failure hooks

By default, listener exceptions bubble up unless a listener is registered with `suppressErrors: true`.

If you want logging, metrics, or alerts around listener failures, attach a failure hook:

```php
use Assegai\Events\EventEmitter;
use Assegai\Events\EventListenerFailure;

$events = new EventEmitter();

$events->onFailure(function (EventListenerFailure $failure): void {
  error_log(sprintf(
    'Event listener failed for %s (%s): %s',
    $failure->eventName,
    $failure->listenerId,
    $failure->throwable->getMessage(),
  ));
});
```

Failure hooks are observational. They run when a listener throws, but they do not replace the normal exception policy.

## Durable delivery with an outbox

This package stays synchronous and in-process on purpose. If the work must survive process restarts or be retried later, record a durable event and let a relay publish it to a queue or broker.

The generic durable seam is `DurableOutboxStoreInterface`:

```php
use Assegai\Events\Interfaces\DurableOutboxStoreInterface;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Events\Outbox\OutboxRecorder;
use DateTimeImmutable;
use Throwable;

final class DatabaseOutboxStore implements DurableOutboxStoreInterface
{
  public function append(OutboxMessage $message): void
  {
    // persist to a database table, message log, or transactional outbox
  }

  public function leasePending(int $limit = 100, ?DateTimeImmutable $now = null): array
  {
    return [];
  }

  public function markDispatched(string|int $id, ?DateTimeImmutable $dispatchedAt = null): void
  {
  }

  public function markFailed(string|int $id, string|Throwable $error, ?DateTimeImmutable $retryAt = null): void
  {
  }
}

$outbox = new OutboxRecorder(new DatabaseOutboxStore());
$outbox->record('orders.created', ['orderId' => 42], ['source' => 'checkout']);
```

For Assegai projects there is also a ready-made bridge:

- `EventsOutboxModule`
- `OrmOutboxStore`
- `AssegaiOutboxRelayService`

```php
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Events\Assegai\Outbox\EventsOutboxModule;

#[Module(
  imports: [EventsOutboxModule::class],
)]
final class AppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
```

Relay configuration lives in `assegai.json`:

```json
{
  "events": {
    "outbox": {
      "queue": "rabbitmq.events",
      "batchSize": 100,
      "retryDelaySeconds": 60
    }
  }
}
```

Then drain the outbox onto the configured queue connection:

```php
use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Assegai\Outbox\AssegaiOutboxRelayService;

#[Injectable]
final class OutboxDrainService
{
  public function __construct(
    private readonly AssegaiOutboxRelayService $relay,
  )
  {
  }

  public function flush(): void
  {
    $this->relay->relayPending();
  }
}
```

One important boundary: the ORM-backed store gives you a real durable table and relay flow, but strict single-transaction outbox guarantees still depend on how your application manages database transactions. If you need the domain write and outbox append to share the exact same transaction, construct the store around a repository or manager that participates in that same unit of work.

## Notes

- Listeners run synchronously in the current process.
- Wildcards are enabled by default.
- This package is designed to stay usable outside AssegaiPHP, so it does not require `assegaiphp/core`.
- In Assegai apps, `#[OnEvent(...)]` listeners should stay application-scoped. Request-scoped listeners are intentionally skipped during bootstrap registration.
