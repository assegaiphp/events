<?php

namespace Assegai\Events\Interfaces;

interface QueuePublisherInterface
{
  public function add(object $job, object|array|null $options = null): void;
}
