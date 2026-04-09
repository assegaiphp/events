<?php

namespace Assegai\Events\Bridge\Outbox;

use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Exceptions\ConfiguredQueueConnectionException;
use Assegai\Events\Interfaces\QueuePublisherInterface;
use Assegai\Events\Outbox\QueuePublisherAdapter;

#[Injectable]
class ConfiguredQueueConnectionFactory
{
  public function create(string $connectionPath): QueuePublisherInterface
  {
    [$driver, $name] = explode('.', $connectionPath, 2) + [null, null];

    if (!is_string($driver) || $driver === '' || !is_string($name) || $name === '') {
      throw new ConfiguredQueueConnectionException("Invalid queue connection path '{$connectionPath}'. Expected '<driver>.<name>'.");
    }

    $drivers = config('queues.drivers', []);
    $connections = config('queues.connections', []);

    if (!is_array($drivers) || !is_array($connections)) {
      throw new ConfiguredQueueConnectionException('Queue configuration is missing.');
    }

    $driverClass = $drivers[$driver] ?? null;
    $connectionConfig = $connections[$driver][$name] ?? null;

    if (!is_string($driverClass) || $driverClass === '') {
      throw new ConfiguredQueueConnectionException("Queue driver '{$driver}' is not configured.");
    }

    if (!is_array($connectionConfig)) {
      throw new ConfiguredQueueConnectionException("Queue connection '{$connectionPath}' is not configured.");
    }

    if (!class_exists($driverClass)) {
      throw new ConfiguredQueueConnectionException("Queue driver class '{$driverClass}' was not found.");
    }

    if (!method_exists($driverClass, 'create')) {
      throw new ConfiguredQueueConnectionException("Queue driver class '{$driverClass}' must expose a static create(array \$config) method.");
    }

    $connectionConfig['name'] ??= $name;
    $queue = $driverClass::create($connectionConfig);

    if ($queue instanceof QueuePublisherInterface) {
      return $queue;
    }

    if (!is_object($queue) || !is_callable([$queue, 'add'])) {
      throw new ConfiguredQueueConnectionException("Queue driver class '{$driverClass}' must create an object with an add() method.");
    }

    return new QueuePublisherAdapter($queue);
  }
}
