<?php

namespace Assegai\Events\Outbox;

use Assegai\Events\Interfaces\QueuePublisherInterface;
use InvalidArgumentException;

final readonly class QueuePublisherAdapter implements QueuePublisherInterface
{
  public function __construct(
    private object $queue,
  )
  {
    if (!is_callable([$this->queue, 'add'])) {
      throw new InvalidArgumentException('The provided queue object must expose an add() method.');
    }
  }

  public function add(object $job, object|array|null $options = null): void
  {
    $this->queue->add($job, $options);
  }

  public function unwrap(): object
  {
    return $this->queue;
  }
}
