<?php

namespace Assegai\Events\Interfaces;

interface ListenerProviderInterface
{
  public function register(object ...$listeners): void;
}
