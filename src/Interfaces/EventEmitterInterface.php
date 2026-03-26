<?php

namespace Assegai\Events\Interfaces;

use Assegai\Events\EventListenerFailure;

interface EventEmitterInterface
{
  public function on(string $event, callable $listener, int $priority = 0, bool $suppressErrors = false): static;

  public function once(string $event, callable $listener, int $priority = 0, bool $suppressErrors = false): static;

  public function onFailure(callable|EventFailureHandlerInterface $handler): static;

  public function off(string $event, callable $listener): void;

  public function clearFailureHandlers(): void;

  /**
   * @return array<int, mixed>
   */
  public function emit(string|object $event, mixed $payload = null): array;

  public function clear(?string $event = null): void;
}
