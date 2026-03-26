<?php

namespace Assegai\Events\Assegai;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\OnApplicationBootstrapInterface;
use Assegai\Core\ModuleManager;
use Assegai\Events\ReflectiveListenerProvider;
use ReflectionClass;
use ReflectionException;

#[Injectable]
class EventListenerRegistrar implements OnApplicationBootstrapInterface
{
  private readonly ReflectiveListenerProvider $listenerProvider;
  private bool $bootstrapped = false;

  public function __construct(
    private readonly AssegaiEventEmitter $eventEmitter,
    private readonly EventEmitterReadinessWatcherProvider $readinessWatcher,
    private readonly ModuleManager $moduleManager,
    private readonly Injector $injector,
  )
  {
    $this->listenerProvider = new ReflectiveListenerProvider($this->eventEmitter);
  }

  public function onApplicationBootstrap(): void
  {
    if ($this->bootstrapped) {
      $this->readinessWatcher->markReady();
      return;
    }

    foreach (array_keys($this->moduleManager->getProviderTokens()) as $providerClass) {
      if ($providerClass === self::class) {
        continue;
      }

      try {
        $reflection = new ReflectionClass($providerClass);
      } catch (ReflectionException) {
        continue;
      }

      if ($this->injector->getDependencyScope($providerClass, $reflection) !== Scope::DEFAULT) {
        continue;
      }

      $instance = $this->injector->resolve($providerClass);

      if (! is_object($instance)) {
        continue;
      }

      $this->listenerProvider->register($instance);
    }

    $this->bootstrapped = true;
    $this->readinessWatcher->markReady();
  }
}
