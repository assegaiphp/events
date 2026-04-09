<?php

namespace Assegai\Events\Exceptions;

final class EventEmitterNotReadyException extends AssegaiEventException
{
  public function __construct(string $message = 'The event emitter is not ready yet.', int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}
