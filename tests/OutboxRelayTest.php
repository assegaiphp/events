<?php

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Common\Interfaces\Queues\QueueProcessResultInterface;
use Assegai\Events\Interfaces\DurableOutboxStoreInterface;
use Assegai\Events\Outbox\OutboxDeliveryStatus;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Events\Outbox\OutboxRecord;
use Assegai\Events\Outbox\OutboxRelayConfig;
use Assegai\Events\Outbox\OutboxRelayService;
use Assegai\Events\Outbox\QueuedOutboxMessage;

it('relays leased outbox messages onto the queue and marks them dispatched', function (): void {
  $store = new InMemoryDurableOutboxStore([
    new OutboxRecord(
      id: 1,
      eventName: 'orders.created',
      payload: ['orderId' => 42],
      headers: ['source' => 'checkout'],
      occurredAt: new DateTimeImmutable('2026-03-26 10:00:00'),
    ),
  ]);
  $queue = new InMemoryQueue();
  $relay = new OutboxRelayService($store, $queue, new OutboxRelayConfig(batchSize: 50, retryDelaySeconds: 30));

  $result = $relay->relayPending();

  expect($result->leased)->toBe(1)
    ->and($result->dispatched)->toBe(1)
    ->and($result->failed)->toBe(0)
    ->and($queue->jobs)->toHaveCount(1)
    ->and($queue->jobs[0])->toBeInstanceOf(QueuedOutboxMessage::class)
    ->and($queue->jobs[0]->eventName)->toBe('orders.created')
    ->and($queue->jobs[0]->payload)->toBe(['orderId' => 42])
    ->and($store->dispatchedIds)->toBe([1]);
});

it('marks failed relay attempts for retry when queue publishing throws', function (): void {
  $store = new InMemoryDurableOutboxStore([
    new OutboxRecord(
      id: 7,
      eventName: 'orders.created',
      payload: ['orderId' => 7],
      headers: [],
      occurredAt: new DateTimeImmutable('2026-03-26 10:00:00'),
    ),
  ]);
  $queue = new InMemoryQueue(throwOnAdd: new RuntimeException('Queue publish failed.'));
  $relay = new OutboxRelayService($store, $queue, new OutboxRelayConfig(batchSize: 10, retryDelaySeconds: 120));

  $result = $relay->relayPending();

  expect($result->leased)->toBe(1)
    ->and($result->dispatched)->toBe(0)
    ->and($result->failed)->toBe(1)
    ->and($result->errors)->toBe(['Queue publish failed.'])
    ->and($store->failed[7]['message'])->toBe('Queue publish failed.')
    ->and($store->failed[7]['retryAt'])->toBeInstanceOf(DateTimeImmutable::class);
});

final class InMemoryDurableOutboxStore implements DurableOutboxStoreInterface
{
  /**
   * @var OutboxRecord[]
   */
  private array $records;

  /**
   * @var array<int, string|int>
   */
  public array $dispatchedIds = [];

  /**
   * @var array<string|int, array{message: string, retryAt: ?DateTimeImmutable}>
   */
  public array $failed = [];

  /**
   * @param OutboxRecord[] $records
   */
  public function __construct(array $records = [])
  {
    $this->records = $records;
  }

  public function append(OutboxMessage $message): void
  {
    $this->records[] = new OutboxRecord(
      id: count($this->records) + 1,
      eventName: $message->eventName,
      payload: $message->payload,
      headers: $message->headers,
      occurredAt: $message->occurredAt,
    );
  }

  public function leasePending(int $limit = 100, ?DateTimeImmutable $now = null): array
  {
    $leased = [];

    foreach ($this->records as $record) {
      if (count($leased) >= $limit) {
        break;
      }

      if ($record->status !== OutboxDeliveryStatus::PENDING) {
        continue;
      }

      $leased[] = new OutboxRecord(
        id: $record->id,
        eventName: $record->eventName,
        payload: $record->payload,
        headers: $record->headers,
        occurredAt: $record->occurredAt,
        availableAt: $record->availableAt,
        attempts: $record->attempts + 1,
        status: OutboxDeliveryStatus::PROCESSING,
        processingStartedAt: $now ?? new DateTimeImmutable(),
      );
    }

    return $leased;
  }

  public function markDispatched(string|int $id, ?DateTimeImmutable $dispatchedAt = null): void
  {
    $this->dispatchedIds[] = $id;
  }

  public function markFailed(string|int $id, string|Throwable $error, ?DateTimeImmutable $retryAt = null): void
  {
    $this->failed[$id] = [
      'message' => $error instanceof Throwable ? $error->getMessage() : $error,
      'retryAt' => $retryAt,
    ];
  }
}

final class InMemoryQueue implements QueueInterface
{
  /**
   * @var object[]
   */
  public array $jobs = [];

  public function __construct(
    private readonly ?Throwable $throwOnAdd = null,
  )
  {
  }

  public function add(object $job, object|array|null $options = null): void
  {
    if ($this->throwOnAdd instanceof Throwable) {
      throw $this->throwOnAdd;
    }

    $this->jobs[] = $job;
  }

  public function process(callable $callback): QueueProcessResultInterface
  {
    return new class implements QueueProcessResultInterface {
      public function getData(): mixed
      {
        return null;
      }

      public function isOk(): bool
      {
        return true;
      }

      public function isError(): bool
      {
        return false;
      }

      public function getErrors(): array
      {
        return [];
      }

      public function getNextError(): ?Throwable
      {
        return null;
      }

      public function getJob(): ?object
      {
        return null;
      }
    };
  }

  public function getName(): string
  {
    return 'in-memory';
  }

  public function getTotalJobs(): int
  {
    return count($this->jobs);
  }

  public static function create(array $config): QueueInterface
  {
    return new self();
  }
}
