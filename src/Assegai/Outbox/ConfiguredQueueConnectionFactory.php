<?php

namespace Assegai\Events\Assegai\Outbox;

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Exceptions\ConfiguredQueueConnectionException;

#[Injectable]
class ConfiguredQueueConnectionFactory
{
  public function create(string $connectionPath): QueueInterface
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

    if (!is_subclass_of($driverClass, QueueInterface::class)) {
      throw new ConfiguredQueueConnectionException("Queue driver class '{$driverClass}' must implement QueueInterface.");
    }

    $connectionConfig['name'] ??= $name;

    return $driverClass::create($connectionConfig);
  }
}
