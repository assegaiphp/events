<?php

namespace Assegai\Events\Outbox;

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Events\Interfaces\DurableOutboxStoreInterface;
use DateTimeImmutable;
use Throwable;

final readonly class OutboxRelayService
{
  public function __construct(
    private DurableOutboxStoreInterface $store,
    private QueueInterface $queue,
    private OutboxRelayConfig $config = new OutboxRelayConfig(),
  )
  {
  }

  public function relayPending(?int $limit = null): OutboxRelayResult
  {
    $effectiveLimit = max(1, $limit ?? $this->config->batchSize);
    $leasedMessages = $this->store->leasePending($effectiveLimit);
    $dispatchedIds = [];
    $failedIds = [];
    $errors = [];

    foreach ($leasedMessages as $message) {
      try {
        $this->queue->add(new QueuedOutboxMessage(
          outboxId: $message->id,
          eventName: $message->eventName,
          payload: $message->payload,
          headers: $message->headers,
          occurredAt: $message->occurredAt,
        ));

        $this->store->markDispatched($message->id);
        $dispatchedIds[] = $message->id;
      } catch (Throwable $throwable) {
        $this->store->markFailed(
          $message->id,
          $throwable,
          (new DateTimeImmutable())->modify('+' . $this->config->retryDelaySeconds . ' seconds'),
        );
        $failedIds[] = $message->id;
        $errors[] = $throwable->getMessage();
      }
    }

    return new OutboxRelayResult(
      leased: count($leasedMessages),
      dispatched: count($dispatchedIds),
      failed: count($failedIds),
      dispatchedIds: $dispatchedIds,
      failedIds: $failedIds,
      errors: $errors,
    );
  }
}
