<?php

namespace Assegai\Events\Exceptions;

final class ListenerLimitExceededException extends AssegaiEventException
{
  public function __construct(string $event, int $maxListeners, int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct(
      sprintf('The event "%s" already has the maximum number of listeners (%d).', $event, $maxListeners),
      $code,
      $previous,
    );
  }
}
