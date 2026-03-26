<?php

namespace Assegai\Events;

use RuntimeException;

class EventEmitterReadinessWatcher
{
  private bool $ready = false;

  public function markReady(): void
  {
    $this->ready = true;
  }

  public function isReady(): bool
  {
    return $this->ready;
  }

  public function reset(): void
  {
    $this->ready = false;
  }

  public function waitUntilReady(int $timeoutMilliseconds = 5000, int $sleepMicroseconds = 1000): void
  {
    $deadline = microtime(true) + max(0, $timeoutMilliseconds) / 1000;

    while (! $this->ready && microtime(true) < $deadline) {
      usleep(max(0, $sleepMicroseconds));
    }

    if (! $this->ready) {
      throw new RuntimeException('The event emitter is not ready yet.');
    }
  }
}
