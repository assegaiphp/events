<?php

namespace Assegai\Events\Interfaces;

interface EventEmitterInterface
{
  public function on(string $event, callable $listener, int $priority = 0, bool $suppressErrors = false): static;

  public function once(string $event, callable $listener, int $priority = 0, bool $suppressErrors = false): static;

  public function off(string $event, callable $listener): void;

  /**
   * @return array<int, mixed>
   */
  public function emit(string|object $event, mixed $payload = null): array;

  public function clear(?string $event = null): void;
}
