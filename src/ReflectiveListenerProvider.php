<?php

namespace Assegai\Events;

use Assegai\Events\Attributes\OnEvent;
use Assegai\Events\Interfaces\EventEmitterInterface;
use Assegai\Events\Interfaces\ListenerProviderInterface;
use ReflectionClass;
use ReflectionMethod;

class ReflectiveListenerProvider implements ListenerProviderInterface
{
  /**
   * @var array<string, true>
   */
  private array $registeredListeners = [];

  public function __construct(
    private readonly EventEmitterInterface $emitter,
  )
  {
  }

  public function register(object ...$listeners): void
  {
    foreach ($listeners as $listener) {
      $this->registerListener($listener);
    }
  }

  private function registerListener(object $listener): void
  {
    $reflection = new ReflectionClass($listener);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      foreach ($method->getAttributes(OnEvent::class) as $attribute) {
        /** @var OnEvent $metadata */
        $metadata = $attribute->newInstance();
        $events = is_array($metadata->event) ? $metadata->event : [$metadata->event];

        foreach ($events as $eventName) {
          $registrationKey = $this->registrationKey($listener, $method, $eventName, $metadata->once);

          if (isset($this->registeredListeners[$registrationKey])) {
            continue;
          }

          if ($metadata->once) {
            $this->emitter->once($eventName, [$listener, $method->getName()], $metadata->priority, $metadata->suppressErrors);
            $this->registeredListeners[$registrationKey] = true;
            continue;
          }

          $this->emitter->on($eventName, [$listener, $method->getName()], $metadata->priority, $metadata->suppressErrors);
          $this->registeredListeners[$registrationKey] = true;
        }
      }
    }
  }

  private function registrationKey(object $listener, ReflectionMethod $method, string $eventName, bool $once): string
  {
    return spl_object_hash($listener) . '::' . $method->getName() . '@' . $eventName . '#once:' . ($once ? '1' : '0');
  }
}
