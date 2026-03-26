<?php

namespace Assegai\Events\Outbox;

use DateTimeImmutable;

readonly class OutboxRecord
{
  /**
   * @param array<string, scalar|array<mixed>|null> $headers
   */
  public function __construct(
    public string|int $id,
    public string $eventName,
    public mixed $payload,
    public array $headers,
    public DateTimeImmutable $occurredAt,
    public ?DateTimeImmutable $availableAt = null,
    public int $attempts = 0,
    public OutboxDeliveryStatus $status = OutboxDeliveryStatus::PENDING,
    public ?DateTimeImmutable $processingStartedAt = null,
    public ?DateTimeImmutable $dispatchedAt = null,
    public ?string $lastError = null,
  )
  {
  }
}
