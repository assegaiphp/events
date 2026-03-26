<?php

namespace Assegai\Events\Assegai\Outbox;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Events\Outbox\OutboxRelayConfig;
use Assegai\Events\Outbox\OutboxRelayResult;
use Assegai\Events\Outbox\OutboxRelayService;

#[Injectable]
class AssegaiOutboxRelayService
{
  public function __construct(
    private readonly OrmOutboxStore $store,
    private readonly ConfiguredQueueConnectionFactory $queueFactory,
    private readonly ?ProjectConfig $projectConfig = null,
  )
  {
  }

  public function relayPending(?int $limit = null): OutboxRelayResult
  {
    $config = new OutboxRelayConfig(
      batchSize: (int) ($this->projectConfig?->get('events.outbox.batchSize', 100) ?? 100),
      retryDelaySeconds: (int) ($this->projectConfig?->get('events.outbox.retryDelaySeconds', 60) ?? 60),
    );
    $queuePath = (string) ($this->projectConfig?->get('events.outbox.queue', 'rabbitmq.events') ?? 'rabbitmq.events');
    $relay = new OutboxRelayService(
      store: $this->store,
      queue: $this->queueFactory->create($queuePath),
      config: $config,
    );

    return $relay->relayPending($limit);
  }
}
