<?php

namespace Assegai\Events\Outbox;

readonly class OutboxRelayResult
{
  /**
   * @param array<int, string|int> $dispatchedIds
   * @param array<int, string|int> $failedIds
   * @param array<int, string> $errors
   */
  public function __construct(
    public int $leased,
    public int $dispatched,
    public int $failed,
    public array $dispatchedIds = [],
    public array $failedIds = [],
    public array $errors = [],
  )
  {
  }
}
