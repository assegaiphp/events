<?php

namespace Assegai\Events;

readonly class ListenerDefinition
{
  /**
   * @param callable $listener
   */
  public function __construct(
    public string $event,
    public mixed $listener,
    public int $priority = 0,
    public bool $once = false,
    public bool $suppressErrors = false,
  )
  {
  }
}
