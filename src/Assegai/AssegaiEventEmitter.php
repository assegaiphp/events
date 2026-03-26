<?php

namespace Assegai\Events\Assegai;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Events\EventEmitter;
use Assegai\Events\EventEmitterConfig;

#[Injectable]
class AssegaiEventEmitter extends EventEmitter
{
  public function __construct(?ProjectConfig $projectConfig = null)
  {
    parent::__construct(
      $projectConfig instanceof ProjectConfig
        ? EventEmitterConfig::fromArray((array) ($projectConfig->get('events', []) ?? []))
        : new EventEmitterConfig(),
    );
  }
}
