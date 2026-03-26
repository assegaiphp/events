<?php

namespace Assegai\Events\Outbox;

readonly class OutboxRelayConfig
{
  public function __construct(
    public int $batchSize = 100,
    public int $retryDelaySeconds = 60,
  )
  {
  }
}
