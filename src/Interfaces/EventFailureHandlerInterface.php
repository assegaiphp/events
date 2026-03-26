<?php

namespace Assegai\Events\Interfaces;

use Assegai\Events\EventListenerFailure;

interface EventFailureHandlerInterface
{
  public function handle(EventListenerFailure $failure): void;
}
