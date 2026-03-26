<?php

namespace Assegai\Events\Support;

final class EventNameResolver
{
  private function __construct()
  {
  }

  public static function resolve(string|object $event): string
  {
    return is_object($event) ? $event::class : $event;
  }
}
