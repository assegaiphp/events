<?php

namespace Assegai\Events;

readonly class EventListenerFailure
{
  /**
   * @param callable $listener
   */
  public function __construct(
    public string $eventName,
    public string $registeredEvent,
    public mixed $listener,
    public string $listenerId,
    public \Throwable $throwable,
    public mixed $primaryArgument = null,
    public ?object $eventObject = null,
    public bool $suppressed = false,
    public bool $once = false,
  )
  {
  }
}
