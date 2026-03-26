<?php

namespace Assegai\Events\Interfaces;

use Assegai\Events\Outbox\OutboxMessage;

interface OutboxStoreInterface
{
  public function append(OutboxMessage $message): void;
}
