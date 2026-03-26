<?php

namespace Tests\EventsOutbox {

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Common\Interfaces\Queues\QueueProcessResultInterface;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Events\Assegai\Outbox\AssegaiOutboxRelayService;
use Assegai\Events\Assegai\Outbox\ConfiguredQueueConnectionFactory;
use Assegai\Events\Assegai\Outbox\Entities\OutboxMessageEntity;
use Assegai\Events\Assegai\Outbox\OrmOutboxStore;
use Assegai\Events\Outbox\OutboxDeliveryStatus;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;
use DateTimeImmutable;
use Throwable;

final class FakeRelayQueue implements QueueInterface
{
  /**
   * @var object[]
   */
  public static array $jobs = [];

  public static function reset(): void
  {
    self::$jobs = [];
  }

  public function __construct(
    private readonly string $name,
  )
  {
  }

  public function add(object $job, object|array|null $options = null): void
  {
    self::$jobs[] = $job;
  }

  public function process(callable $callback): QueueProcessResultInterface
  {
    return new class implements QueueProcessResultInterface {
      public function getData(): mixed
      {
        return null;
      }

      public function isOk(): bool
      {
        return true;
      }

      public function isError(): bool
      {
        return false;
      }

      public function getErrors(): array
      {
        return [];
      }

      public function getNextError(): ?Throwable
      {
        return null;
      }

      public function getJob(): ?object
      {
        return null;
      }
    };
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getTotalJobs(): int
  {
    return count(self::$jobs);
  }

  public static function create(array $config): QueueInterface
  {
    return new self((string) ($config['name'] ?? 'events'));
  }
}
}

namespace {

use Assegai\Events\Assegai\Outbox\AssegaiOutboxRelayService;
use Assegai\Events\Assegai\Outbox\ConfiguredQueueConnectionFactory;
use Assegai\Events\Assegai\Outbox\Entities\OutboxMessageEntity;
use Assegai\Events\Assegai\Outbox\OrmOutboxStore;
use Assegai\Events\Outbox\OutboxDeliveryStatus;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;
use Tests\EventsOutbox\FakeRelayQueue;

beforeEach(function (): void {
  $this->originalWorkingDirectory = getcwd() ?: '.';
  $this->workspace = sys_get_temp_dir() . '/assegai-events-outbox-' . bin2hex(random_bytes(6));
  $this->configDirectory = $this->workspace . '/config';
  $this->dbPath = $this->workspace . '/storage/events.sqlite';

  @mkdir($this->configDirectory, 0777, true);
  @mkdir(dirname($this->dbPath), 0777, true);

  file_put_contents($this->workspace . '/composer.json', json_encode([
    'name' => 'tests/events-outbox-app',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($this->workspace . '/assegai.json', json_encode([
    'events' => [
      'outbox' => [
        'queue' => 'sync.events',
        'batchSize' => 20,
        'retryDelaySeconds' => 45,
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($this->configDirectory . '/default.php', "<?php\n\nreturn [];\n");
  file_put_contents($this->configDirectory . '/queues.php', <<<PHP
<?php

use Tests\EventsOutbox\FakeRelayQueue;

return [
  'drivers' => [
    'sync' => FakeRelayQueue::class,
  ],
  'connections' => [
    'sync' => [
      'events' => [
        'name' => 'events',
      ],
    ],
  ],
];
PHP);

  chdir($this->workspace);
  FakeRelayQueue::reset();
  \Assegai\Core\Config::set('databases', [
    'sqlite' => [
      'events-outbox-test' => [
        'path' => $this->dbPath,
      ],
    ],
  ]);

  $this->dataSource = new DataSource(new DataSourceOptions(
    entities: [OutboxMessageEntity::class],
    name: 'events-outbox-test',
    type: DataSourceType::SQLITE,
  ));
  $this->repository = $this->dataSource->getRepository(OutboxMessageEntity::class);
  $this->dataSource->getClient()->exec('DROP TABLE IF EXISTS event_outbox');
  $this->dataSource->getClient()->exec(
    "CREATE TABLE event_outbox (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      eventName VARCHAR(191) NOT NULL,
      payloadJson TEXT NOT NULL,
      headersJson TEXT NOT NULL,
      status VARCHAR(32) NOT NULL DEFAULT 'pending',
      attempts INTEGER NOT NULL DEFAULT 0,
      occurredAt DATETIME NOT NULL,
      availableAt DATETIME NULL,
      processingStartedAt DATETIME NULL,
      dispatchedAt DATETIME NULL,
      lastError TEXT NULL,
      createdAt DATETIME NULL,
      updatedAt DATETIME NULL,
      deletedAt DATETIME NULL
    )"
  );
});

afterEach(function (): void {
  unset($this->repository, $this->dataSource);

  if (isset($this->dbPath) && is_file($this->dbPath)) {
    @unlink($this->dbPath);
  }

  if (isset($this->workspace) && is_dir($this->workspace)) {
    removeEventsOutboxDirectoryTree($this->workspace);
  }

  if (isset($this->originalWorkingDirectory) && is_dir($this->originalWorkingDirectory)) {
    chdir($this->originalWorkingDirectory);
  }
});

it('persists and leases durable outbox rows through the ORM store', function (): void {
  $store = new OrmOutboxStore($this->repository);

  $store->append(new OutboxMessage(
    eventName: 'orders.created',
    payload: ['orderId' => 10],
    headers: ['source' => 'checkout'],
    occurredAt: new DateTimeImmutable('2026-03-26 10:00:00'),
  ));

  $leased = $store->leasePending(10, new DateTimeImmutable('2026-03-26 10:05:00'));

  expect($leased)->toHaveCount(1)
    ->and($leased[0]->eventName)->toBe('orders.created')
    ->and($leased[0]->payload)->toBe(['orderId' => 10])
    ->and($leased[0]->status)->toBe(OutboxDeliveryStatus::PROCESSING)
    ->and($leased[0]->attempts)->toBe(1);
});

it('marks dispatched and failed rows with the expected status transitions', function (): void {
  $store = new OrmOutboxStore($this->repository);
  $store->append(new OutboxMessage(
    eventName: 'orders.created',
    payload: ['orderId' => 11],
    occurredAt: new DateTimeImmutable('2026-03-26 10:00:00'),
  ));

  $leased = $store->leasePending(10, new DateTimeImmutable('2026-03-26 10:05:00'));
  $store->markFailed($leased[0]->id, 'Temporary publish failure.', new DateTimeImmutable('2026-03-26 10:06:00'));
  $rowAfterFailure = $this->dataSource->getClient()
    ->query('SELECT status, lastError, availableAt FROM event_outbox WHERE id = 1')
    ->fetch();

  expect($rowAfterFailure['status'])->toBe(OutboxDeliveryStatus::PENDING->value)
    ->and($rowAfterFailure['lastError'])->toBe('Temporary publish failure.')
    ->and($rowAfterFailure['availableAt'])->toContain('2026-03-26 10:06:00');

  $leasedAgain = $store->leasePending(10, new DateTimeImmutable('2026-03-26 10:06:00'));
  $store->markDispatched($leasedAgain[0]->id, new DateTimeImmutable('2026-03-26 10:07:00'));
  $rowAfterDispatch = $this->dataSource->getClient()
    ->query('SELECT status, dispatchedAt, processingStartedAt FROM event_outbox WHERE id = 1')
    ->fetch();

  expect($rowAfterDispatch['status'])->toBe(OutboxDeliveryStatus::DISPATCHED->value)
    ->and($rowAfterDispatch['dispatchedAt'])->toContain('2026-03-26 10:07:00')
    ->and($rowAfterDispatch['processingStartedAt'])->toBeNull();
});

it('relays persisted outbox rows through the configured queue connection', function (): void {
  $store = new OrmOutboxStore($this->repository);
  $store->append(new OutboxMessage(
    eventName: 'orders.created',
    payload: ['orderId' => 12],
    headers: ['source' => 'kitchen'],
    occurredAt: new DateTimeImmutable('2026-03-26 10:00:00'),
  ));

  $service = new AssegaiOutboxRelayService(
    $store,
    new ConfiguredQueueConnectionFactory(),
    new \Assegai\Core\Config\ProjectConfig(),
  );

  $result = $service->relayPending();
  $row = $this->dataSource->getClient()
    ->query('SELECT status FROM event_outbox WHERE id = 1')
    ->fetch();

  expect($result->dispatched)->toBe(1)
    ->and(FakeRelayQueue::$jobs)->toHaveCount(1)
    ->and(FakeRelayQueue::$jobs[0]->eventName)->toBe('orders.created')
    ->and(FakeRelayQueue::$jobs[0]->payload)->toBe(['orderId' => 12])
    ->and($row['status'])->toBe(OutboxDeliveryStatus::DISPATCHED->value);
});

function removeEventsOutboxDirectoryTree(string $path): void
{
  if (!is_dir($path)) {
    return;
  }

  foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
    $itemPath = $path . '/' . $item;

    if (is_dir($itemPath)) {
      removeEventsOutboxDirectoryTree($itemPath);
      continue;
    }

    @unlink($itemPath);
  }

  @rmdir($path);
}
}
