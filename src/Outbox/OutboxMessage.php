<?php

namespace Assegai\Events\Outbox;

readonly class OutboxMessage
{
  /**
   * @param array<string, scalar|array<mixed>|null> $headers
   */
  public function __construct(
    public string $eventName,
    public mixed $payload,
    public array $headers = [],
    public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
  )
  {
  }
}
