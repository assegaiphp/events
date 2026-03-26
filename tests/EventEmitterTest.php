<?php
use Assegai\Events\Attributes\OnEvent;
use Assegai\Events\EventEmitter;
use Assegai\Events\EventEmitterConfig;
use Assegai\Events\EventListenerFailure;
use Assegai\Events\EventEmitterReadinessWatcher;
use Assegai\Events\Interfaces\OutboxStoreInterface;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Events\Outbox\OutboxRecorder;
use Assegai\Events\ReflectiveListenerProvider;

it('emits exact named events', function (): void {
  $emitter = new EventEmitter();
  $calls = [];

  $emitter->on('orders.created', function (array $payload) use (&$calls): void {
    $calls[] = $payload['orderId'];
  });

  $emitter->emit('orders.created', ['orderId' => 42]);

  expect($calls)->toBe([42]);
});

it('supports once listeners', function (): void {
  $emitter = new EventEmitter();
  $calls = 0;

  $emitter->once('orders.created', function () use (&$calls): void {
    $calls++;
  });

  $emitter->emit('orders.created');
  $emitter->emit('orders.created');

  expect($calls)->toBe(1);
});

it('supports invokable object listeners', function (): void {
  $emitter = new EventEmitter();
  $listener = new class {
    /**
     * @var array<int, int>
     */
    public array $received = [];

    public function __invoke(array $payload): void
    {
      $this->received[] = $payload['orderId'];
    }
  };

  $emitter->on('orders.created', $listener);
  $emitter->emit('orders.created', ['orderId' => 55]);

  expect($listener->received)->toBe([55]);
});

it('removes only the once registration when the same callable is also persistent', function (): void {
  $emitter = new EventEmitter();
  $calls = 0;
  $listener = function () use (&$calls): void {
    $calls++;
  };

  $emitter->on('orders.created', $listener);
  $emitter->once('orders.created', $listener);

  $emitter->emit('orders.created');
  $emitter->emit('orders.created');

  expect($calls)->toBe(3);
});

it('orders listeners by priority', function (): void {
  $emitter = new EventEmitter();
  $calls = [];

  $emitter->on('orders.created', function () use (&$calls): void {
    $calls[] = 'low';
  }, 10);

  $emitter->on('orders.created', function () use (&$calls): void {
    $calls[] = 'high';
  }, 50);

  $emitter->emit('orders.created');

  expect($calls)->toBe(['high', 'low']);
});

it('supports wildcard event names', function (): void {
  $emitter = new EventEmitter(new EventEmitterConfig(wildcards: true));
  $calls = [];

  $emitter->on('orders.*', function (array $payload, string $eventName) use (&$calls): void {
    $calls[] = $eventName . ':' . $payload['orderId'];
  });

  $emitter->emit('orders.created', ['orderId' => 1]);
  $emitter->emit('orders.cancelled', ['orderId' => 2]);

  expect($calls)->toBe([
    'orders.created:1',
    'orders.cancelled:2',
  ]);
});

it('can dispatch event objects by class name', function (): void {
  $emitter = new EventEmitter();
  $calls = [];

  $emitter->on(OrderCreated::class, function (OrderCreated $event) use (&$calls): void {
    $calls[] = $event->orderId;
  });

  $emitter->emit(new OrderCreated(99));

  expect($calls)->toBe([99]);
});

it('can register listener classes with OnEvent attributes', function (): void {
  $emitter = new EventEmitter();
  $provider = new ReflectiveListenerProvider($emitter);
  $listener = new OrderListener();

  $provider->register($listener);

  $emitter->emit('orders.created', ['orderId' => 7]);
  $emitter->emit(new OrderCreated(8));

  expect($listener->received)->toBe([
    'named:7',
    'object:8',
  ]);
});

it('honors suppressErrors on OnEvent attributes', function (): void {
  $emitter = new EventEmitter();
  $provider = new ReflectiveListenerProvider($emitter);
  $listener = new SuppressedOrderListener();

  $provider->register($listener);
  $emitter->emit('orders.failed');

  expect($listener->afterFailureWasReached)->toBeTrue();
});

it('captures listener failures with failure hooks', function (): void {
  $emitter = new EventEmitter();
  $failures = [];

  $emitter->onFailure(function (EventListenerFailure $failure) use (&$failures): void {
    $failures[] = $failure;
  });

  $emitter->on('orders.failed', function (): void {
    throw new RuntimeException('Listener exploded.');
  });

  expect(fn () => $emitter->emit('orders.failed'))
    ->toThrow(RuntimeException::class, 'Listener exploded.');

  expect($failures)->toHaveCount(1)
    ->and($failures[0]->eventName)->toBe('orders.failed')
    ->and($failures[0]->suppressed)->toBeFalse();
});

it('does not duplicate reflective listener registrations for the same instance', function (): void {
  $emitter = new EventEmitter();
  $provider = new ReflectiveListenerProvider($emitter);
  $listener = new OrderListener();

  $provider->register($listener);
  $provider->register($listener);

  $emitter->emit('orders.created', ['orderId' => 9]);

  expect($listener->received)->toBe(['named:9']);
});

it('enforces the configured listener limit', function (): void {
  $emitter = new EventEmitter(new EventEmitterConfig(maxListeners: 1));

  $emitter->on('orders.created', fn () => null);

  expect(fn () => $emitter->on('orders.created', fn () => null))
    ->toThrow(OverflowException::class);
});

it('can wait for readiness', function (): void {
  $watcher = new EventEmitterReadinessWatcher();

  $watcher->markReady();
  $watcher->waitUntilReady(5);

  expect($watcher->isReady())->toBeTrue();
});

it('can record durable events into an outbox store', function (): void {
  $collector = new \ArrayObject();
  $store = new class($collector) implements OutboxStoreInterface {
    public function __construct(
      private \ArrayObject $collector,
    )
    {
    }

    public function append(OutboxMessage $message): void
    {
      $this->collector->append($message);
    }
  };

  $outbox = new OutboxRecorder($store);
  $message = $outbox->record('orders.created', ['orderId' => 42], ['source' => 'tests']);

  expect($collector)->toHaveCount(1)
    ->and($collector[0])->toBe($message)
    ->and($message->eventName)->toBe('orders.created')
    ->and($message->payload)->toBe(['orderId' => 42]);
});

final readonly class OrderCreated
{
  public function __construct(
    public int $orderId,
  )
  {
  }
}

final class OrderListener
{
  /**
   * @var array<int, string>
   */
  public array $received = [];

  #[OnEvent('orders.created')]
  public function onNamedEvent(array $payload): void
  {
    $this->received[] = 'named:' . $payload['orderId'];
  }

  #[OnEvent(OrderCreated::class)]
  public function onObjectEvent(OrderCreated $event): void
  {
    $this->received[] = 'object:' . $event->orderId;
  }
}

final class SuppressedOrderListener
{
  public bool $afterFailureWasReached = false;

  #[OnEvent('orders.failed', suppressErrors: true)]
  public function failSilently(): void
  {
    throw new RuntimeException('Ignore this listener failure.');
  }

  #[OnEvent('orders.failed')]
  public function afterFailure(): void
  {
    $this->afterFailureWasReached = true;
  }
}
