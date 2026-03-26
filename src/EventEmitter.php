<?php

namespace Assegai\Events;

use Assegai\Events\Interfaces\EventEmitterInterface;
use Assegai\Events\Support\EventNameResolver;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class EventEmitter implements EventEmitterInterface
{
  /**
   * @var array<string, array<int, ListenerDefinition>>
   */
  private array $listeners = [];

  public function __construct(
    private readonly EventEmitterConfig $config = new EventEmitterConfig(),
  )
  {
  }

  public function on(string $event, callable $listener, int $priority = 0): static
  {
    $this->guardListenerLimit($event);
    $this->listeners[$event] ??= [];
    $this->listeners[$event][] = new ListenerDefinition($event, $listener, $priority, false);
    $this->sortListeners($event);

    return $this;
  }

  public function once(string $event, callable $listener, int $priority = 0): static
  {
    $this->guardListenerLimit($event);
    $this->listeners[$event] ??= [];
    $this->listeners[$event][] = new ListenerDefinition($event, $listener, $priority, true);
    $this->sortListeners($event);

    return $this;
  }

  public function off(string $event, callable $listener): void
  {
    if (!isset($this->listeners[$event])) {
      return;
    }

    $listenerId = $this->listenerId($listener);
    $this->listeners[$event] = array_values(array_filter(
      $this->listeners[$event],
      fn (ListenerDefinition $definition): bool => $this->listenerId($definition->listener) !== $listenerId,
    ));

    if ($this->listeners[$event] === []) {
      unset($this->listeners[$event]);
    }
  }

  public function emit(string|object $event, mixed $payload = null): array
  {
    $eventName = EventNameResolver::resolve($event);
    $eventObject = is_object($event) ? $event : null;
    $primaryArgument = $eventObject ?? $payload;
    $matchedListeners = $this->listenersFor($eventName);
    $results = [];

    foreach ($matchedListeners as $definition) {
      try {
        $results[] = $this->invokeListener(
          listener: $definition->listener,
          primaryArgument: $primaryArgument,
          eventName: $eventName,
          eventObject: $eventObject,
        );
      } catch (\Throwable $throwable) {
        if (! $definition->suppressErrors) {
          throw $throwable;
        }
      }

      if ($definition->once) {
        $this->off($definition->event, $definition->listener);
      }
    }

    return $results;
  }

  public function clear(?string $event = null): void
  {
    if (null === $event) {
      $this->listeners = [];
      return;
    }

    unset($this->listeners[$event]);
  }

  /**
   * @return array<int, ListenerDefinition>
   */
  public function listenersFor(string $eventName): array
  {
    $matched = [];

    foreach ($this->listeners as $pattern => $definitions) {
      if (!$this->matches($pattern, $eventName)) {
        continue;
      }

      foreach ($definitions as $definition) {
        $matched[] = $definition;
      }
    }

    usort($matched, fn (ListenerDefinition $left, ListenerDefinition $right): int => $right->priority <=> $left->priority);

    return $matched;
  }

  private function sortListeners(string $event): void
  {
    usort(
      $this->listeners[$event],
      fn (ListenerDefinition $left, ListenerDefinition $right): int => $right->priority <=> $left->priority,
    );
  }

  private function guardListenerLimit(string $event): void
  {
    if ($this->config->maxListeners === null) {
      return;
    }

    $existingCount = count($this->listeners[$event] ?? []);

    if ($existingCount < $this->config->maxListeners) {
      return;
    }

    throw new \OverflowException(sprintf(
      'The event "%s" already has the maximum number of listeners (%d).',
      $event,
      $this->config->maxListeners,
    ));
  }

  private function matches(string $pattern, string $eventName): bool
  {
    if ($pattern === $eventName) {
      return true;
    }

    if (!$this->config->wildcards || (!str_contains($pattern, '*') && !str_contains($pattern, '**'))) {
      return false;
    }

    $quotedDelimiter = preg_quote($this->config->delimiter, '/');
    $expression = preg_quote($pattern, '/');
    $expression = str_replace('\*\*', '.*', $expression);
    $expression = str_replace('\*', '[^' . $quotedDelimiter . ']+', $expression);

    return 1 === preg_match('/^' . $expression . '$/', $eventName);
  }

  private function listenerId(callable $listener): string
  {
    if (is_array($listener)) {
      $target = is_object($listener[0]) ? spl_object_hash($listener[0]) : (string) $listener[0];
      return $target . '::' . (string) $listener[1];
    }

    if ($listener instanceof \Closure) {
      return spl_object_hash($listener);
    }

    return (string) $listener;
  }

  private function invokeListener(
    callable $listener,
    mixed $primaryArgument,
    string $eventName,
    ?object $eventObject,
  ): mixed
  {
    $reflection = $this->reflectCallable($listener);
    $parameters = $reflection->getParameters();

    if ($parameters === []) {
      return $listener();
    }

    if (count($parameters) === 1) {
      return $listener($primaryArgument);
    }

    if (count($parameters) === 2) {
      return $listener($primaryArgument, $eventName);
    }

    return $listener($primaryArgument, $eventName, $eventObject);
  }

  private function reflectCallable(callable $listener): ReflectionFunctionAbstract
  {
    if (is_array($listener)) {
      return new ReflectionMethod($listener[0], (string) $listener[1]);
    }

    if (is_string($listener) && str_contains($listener, '::')) {
      [$className, $method] = explode('::', $listener, 2);
      return new ReflectionMethod($className, $method);
    }

    return new ReflectionFunction($listener);
  }
}
