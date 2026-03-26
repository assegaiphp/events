<?php

use Assegai\Events\Attributes\OnEvent;
use Assegai\Events\EventEmitter;
use Assegai\Events\EventEmitterConfig;
use Assegai\Events\EventEmitterReadinessWatcher;
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
