<?php

namespace Assegai\Events\Interfaces;

use Assegai\Events\Outbox\OutboxRecord;
use DateTimeImmutable;
use Throwable;

interface DurableOutboxStoreInterface extends OutboxStoreInterface
{
  /**
   * @return OutboxRecord[]
   */
  public function leasePending(int $limit = 100, ?DateTimeImmutable $now = null): array;

  public function markDispatched(string|int $id, ?DateTimeImmutable $dispatchedAt = null): void;

  public function markFailed(string|int $id, string|Throwable $error, ?DateTimeImmutable $retryAt = null): void;
}
