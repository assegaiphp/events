<?php

namespace Assegai\Events;

use Assegai\Events\Attributes\OnEvent;
use Assegai\Events\Interfaces\EventEmitterInterface;
use Assegai\Events\Interfaces\ListenerProviderInterface;
use ReflectionClass;
use ReflectionMethod;

class ReflectiveListenerProvider implements ListenerProviderInterface
{
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
          if ($metadata->once) {
            $this->emitter->once($eventName, [$listener, $method->getName()], $metadata->priority, $metadata->suppressErrors);
            continue;
          }

          $this->emitter->on($eventName, [$listener, $method->getName()], $metadata->priority, $metadata->suppressErrors);
        }
      }
    }
  }
}
