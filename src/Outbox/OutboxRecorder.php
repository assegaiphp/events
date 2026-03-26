<?php

namespace Assegai\Events\Outbox;

use Assegai\Events\Interfaces\OutboxStoreInterface;
use Assegai\Events\Support\EventNameResolver;

final readonly class OutboxRecorder
{
  public function __construct(
    private OutboxStoreInterface $store,
  )
  {
  }

  /**
   * @param array<string, scalar|array<mixed>|null> $headers
   */
  public function record(string|object $event, mixed $payload = null, array $headers = []): OutboxMessage
  {
    $message = new OutboxMessage(
      eventName: EventNameResolver::resolve($event),
      payload: is_object($event) ? $event : $payload,
      headers: $headers,
    );

    $this->store->append($message);

    return $message;
  }
}
