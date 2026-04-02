<?php

namespace Assegai\Events\Assegai\Outbox;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Events\Assegai\EventsModule;
use Assegai\Orm\Assegai\OrmModule;

#[Module(
  imports: [
    EventsModule::class,
    OrmModule::class,
  ],
  providers: [
    OrmOutboxStore::class,
    ConfiguredQueueConnectionFactory::class,
    AssegaiOutboxRelayService::class,
  ],
  exports: [
    OrmOutboxStore::class,
    AssegaiOutboxRelayService::class,
  ],
)]
class EventsOutboxModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
