<?php

namespace Assegai\Events\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class OnEvent
{
  /**
   * @param string|array<int, string> $event
   */
  public function __construct(
    public string|array $event,
    public int $priority = 0,
    public bool $once = false,
    public bool $suppressErrors = false,
  )
  {
  }
}
