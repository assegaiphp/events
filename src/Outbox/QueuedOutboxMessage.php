<?php

namespace Assegai\Events\Outbox;

use DateTimeImmutable;

readonly class QueuedOutboxMessage
{
  /**
   * @param array<string, scalar|array<mixed>|null> $headers
   */
  public function __construct(
    public string|int $outboxId,
    public string $eventName,
    public mixed $payload,
    public array $headers = [],
    public ?DateTimeImmutable $occurredAt = null,
  )
  {
  }
}
