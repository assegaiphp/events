<?php

namespace Assegai\Events\Assegai\Outbox;

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Core\Attributes\Injectable;
use RuntimeException;

#[Injectable]
class ConfiguredQueueConnectionFactory
{
  public function create(string $connectionPath): QueueInterface
  {
    [$driver, $name] = explode('.', $connectionPath, 2) + [null, null];

    if (!is_string($driver) || $driver === '' || !is_string($name) || $name === '') {
      throw new RuntimeException("Invalid queue connection path '{$connectionPath}'. Expected '<driver>.<name>'.");
    }

    $drivers = config('queues.drivers', []);
    $connections = config('queues.connections', []);

    if (!is_array($drivers) || !is_array($connections)) {
      throw new RuntimeException('Queue configuration is missing.');
    }

    $driverClass = $drivers[$driver] ?? null;
    $connectionConfig = $connections[$driver][$name] ?? null;

    if (!is_string($driverClass) || $driverClass === '') {
      throw new RuntimeException("Queue driver '{$driver}' is not configured.");
    }

    if (!is_array($connectionConfig)) {
      throw new RuntimeException("Queue connection '{$connectionPath}' is not configured.");
    }

    if (!class_exists($driverClass)) {
      throw new RuntimeException("Queue driver class '{$driverClass}' was not found.");
    }

    if (!is_subclass_of($driverClass, QueueInterface::class)) {
      throw new RuntimeException("Queue driver class '{$driverClass}' must implement QueueInterface.");
    }

    $connectionConfig['name'] ??= $name;

    return $driverClass::create($connectionConfig);
  }
}
