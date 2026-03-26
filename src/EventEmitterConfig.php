<?php

namespace Assegai\Events;

readonly class EventEmitterConfig
{
  public function __construct(
    public bool $wildcards = true,
    public string $delimiter = '.',
    public ?int $maxListeners = null,
  )
  {
  }

  /**
   * @param array<string, mixed> $config
   */
  public static function fromArray(array $config): self
  {
    return new self(
      wildcards: (bool) ($config['wildcards'] ?? $config['wildcard'] ?? true),
      delimiter: (string) ($config['delimiter'] ?? '.'),
      maxListeners: isset($config['maxListeners']) ? (int) $config['maxListeners'] : null,
    );
  }
}
