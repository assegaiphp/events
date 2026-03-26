<?php

namespace Assegai\Events\Outbox;

enum OutboxDeliveryStatus: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case DISPATCHED = 'dispatched';
  case FAILED = 'failed';
}
