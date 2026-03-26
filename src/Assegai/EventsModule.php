<?php

namespace Assegai\Events\Assegai;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;

#[Module(
  providers: [
    AssegaiEventEmitter::class,
    EventEmitterReadinessWatcherProvider::class,
    EventListenerRegistrar::class,
  ],
  exports: [
    AssegaiEventEmitter::class,
    EventEmitterReadinessWatcherProvider::class,
  ],
)]
class EventsModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
