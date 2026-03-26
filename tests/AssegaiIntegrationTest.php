<?php

namespace Tests\EventsAssegai {

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Events\Assegai\AssegaiEventEmitter;
use Assegai\Events\Assegai\EventsModule;
use Assegai\Events\Attributes\OnEvent;

final class EventsBridgeState
{
  /**
   * @var array<int, int>
   */
  public static array $received = [];

  public static int $requestScopedCalls = 0;

  public static function reset(): void
  {
    self::$received = [];
    self::$requestScopedCalls = 0;
  }
}

#[Injectable]
final class OrderWorkflowService
{
  public function __construct(
    private readonly AssegaiEventEmitter $events,
  )
  {
  }

  public function emitCreated(int $orderId): void
  {
    $this->events->emit('orders.created', ['orderId' => $orderId]);
  }
}

#[Injectable]
final class OrderCreatedListener
{
  #[OnEvent('orders.created')]
  public function handle(array $payload): void
  {
    EventsBridgeState::$received[] = $payload['orderId'];
  }
}

#[Injectable(options: ['scope' => Scope::REQUEST, 'durable' => false])]
final class RequestScopedOrderCreatedListener
{
  #[OnEvent('orders.created')]
  public function handle(array $payload): void
  {
    EventsBridgeState::$requestScopedCalls++;
  }
}

#[Module(
  imports: [EventsModule::class],
  providers: [
    OrderWorkflowService::class,
    OrderCreatedListener::class,
    RequestScopedOrderCreatedListener::class,
  ],
)]
final class EventsTestAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
}

namespace {

use Assegai\Core\ControllerManager;
use Assegai\Core\Injector;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Events\Assegai\EventEmitterReadinessWatcherProvider;
use Assegai\Events\Assegai\EventListenerRegistrar;
use Tests\EventsAssegai\EventsBridgeState;
use Tests\EventsAssegai\EventsTestAppModule;
use Tests\EventsAssegai\OrderWorkflowService;

beforeEach(function (): void {
  $this->originalWorkingDirectory = getcwd() ?: '.';
  $this->workingDirectory = sys_get_temp_dir() . '/assegai-events-tests-' . bin2hex(random_bytes(6));
  $this->configDirectory = $this->workingDirectory . '/config';

  @mkdir($this->configDirectory, 0777, true);

  file_put_contents($this->workingDirectory . '/composer.json', json_encode([
    'name' => 'tests/events-app',
    'version' => '0.1.0',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($this->workingDirectory . '/assegai.json', json_encode([
    'events' => [
      'wildcards' => true,
      'delimiter' => '.',
      'maxListeners' => 25,
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($this->configDirectory . '/default.php', "<?php\n\nreturn [];\n");

  chdir($this->workingDirectory);
  resetEventsCoreSingletons();
  EventsBridgeState::reset();
});

afterEach(function (): void {
  resetEventsCoreSingletons();
  EventsBridgeState::reset();

  if (isset($this->workingDirectory) && is_dir($this->workingDirectory)) {
    removeDirectoryTree($this->workingDirectory);
  }

  if (isset($this->originalWorkingDirectory) && is_dir($this->originalWorkingDirectory)) {
    chdir($this->originalWorkingDirectory);
  }
});

it('registers application-scoped listeners during bootstrap, skips request-scoped listeners, and marks readiness', function (): void {
  [$injector, $moduleManager] = buildEventsGraph();
  $watcher = $injector->resolve(EventEmitterReadinessWatcherProvider::class);
  $service = $injector->resolve(OrderWorkflowService::class);
  $registrar = $injector->resolve(EventListenerRegistrar::class);

  expect($watcher->isReady())->toBeFalse();

  $service->emitCreated(1);
  expect(EventsBridgeState::$received)->toBe([])
    ->and(EventsBridgeState::$requestScopedCalls)->toBe(0);

  $registrar->onApplicationBootstrap();

  expect($watcher->isReady())->toBeTrue();

  $service->emitCreated(2);

  expect(EventsBridgeState::$received)->toBe([2])
    ->and(EventsBridgeState::$requestScopedCalls)->toBe(0);
});

it('does not duplicate declarative listeners when bootstrap registration runs more than once', function (): void {
  [$injector, $moduleManager] = buildEventsGraph();
  $registrar = $injector->resolve(EventListenerRegistrar::class);
  $service = $injector->resolve(OrderWorkflowService::class);

  $registrar->onApplicationBootstrap();
  $registrar->onApplicationBootstrap();
  $service->emitCreated(42);

  expect(EventsBridgeState::$received)->toBe([42]);
});

/**
 * @return array{Injector, ModuleManager}
 */
function buildEventsGraph(): array
{
  $injector = Injector::createFresh();
  $moduleManager = ModuleManager::createFresh($injector);

  $injector->add(Injector::class, $injector);
  $injector->add(ModuleManager::class, $moduleManager);

  $moduleManager->setRootModuleClass(EventsTestAppModule::class);
  $moduleManager->buildModuleTokensList(EventsTestAppModule::class);
  $moduleManager->buildProviderTokensList();

  return [$injector, $moduleManager];
}

function resetEventsCoreSingletons(): void
{
  foreach ([Injector::class, ModuleManager::class, ControllerManager::class, Router::class] as $class) {
    $property = new ReflectionProperty($class, 'instance');
    $property->setValue(null, null);
  }

  RuntimeContext::flush();
}

function removeDirectoryTree(string $path): void
{
  if (!is_dir($path)) {
    return;
  }

  foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
    $itemPath = $path . '/' . $item;

    if (is_dir($itemPath)) {
      removeDirectoryTree($itemPath);
      continue;
    }

    @unlink($itemPath);
  }

  @rmdir($path);
}

}
