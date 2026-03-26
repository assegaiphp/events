<?php

namespace Assegai\Events\Assegai\Outbox;

use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Assegai\Outbox\Entities\OutboxMessageEntity;
use Assegai\Events\Interfaces\DurableOutboxStoreInterface;
use Assegai\Events\Outbox\OutboxDeliveryStatus;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Events\Outbox\OutboxRecord;
use Assegai\Orm\Attributes\InjectRepository;
use Assegai\Orm\Management\Repository;
use DateTime;
use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;

#[Injectable]
class OrmOutboxStore implements DurableOutboxStoreInterface
{
  private const string TABLE = 'event_outbox';
  private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

  public function __construct(
    #[InjectRepository(OutboxMessageEntity::class)]
    private readonly Repository $repository,
  )
  {
  }

  public function append(OutboxMessage $message): void
  {
    $timestamp = $message->occurredAt->format(self::DATE_TIME_FORMAT);
    $statement = $this->repository->manager->query(
      'INSERT INTO ' . self::TABLE . ' (
         eventName,
         payloadJson,
         headersJson,
         status,
         attempts,
         occurredAt,
         availableAt,
         processingStartedAt,
         dispatchedAt,
         lastError,
         createdAt,
         updatedAt,
         deletedAt
       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $message->eventName,
        $this->encodeJson($message->payload),
        $this->encodeJson($message->headers),
        OutboxDeliveryStatus::PENDING->value,
        0,
        $timestamp,
        $timestamp,
        null,
        null,
        null,
        $timestamp,
        $timestamp,
        null,
      ],
    );

    if ($statement === false) {
      throw new RuntimeException('Failed to append event outbox message.');
    }
  }

  public function leasePending(int $limit = 100, ?DateTimeImmutable $now = null): array
  {
    $effectiveNow = $now ?? new DateTimeImmutable();
    $rows = $this->repository->manager->query(
      'SELECT id, eventName, payloadJson, headersJson, status, attempts, occurredAt, availableAt, processingStartedAt, dispatchedAt, lastError
       FROM ' . self::TABLE . '
       WHERE status = ? AND deletedAt IS NULL AND (availableAt IS NULL OR availableAt <= ?)
       ORDER BY availableAt ASC, id ASC
       LIMIT ' . max(1, $limit),
      [
        OutboxDeliveryStatus::PENDING->value,
        $effectiveNow->format(self::DATE_TIME_FORMAT),
      ],
    )?->fetchAll();

    if (!is_array($rows) || $rows === []) {
      return [];
    }

    $leased = [];

    foreach ($rows as $row) {
      if (!is_array($row) || !isset($row['id'])) {
        continue;
      }

      $updatedAt = $effectiveNow->format(self::DATE_TIME_FORMAT);
      $statement = $this->repository->manager->query(
        'UPDATE ' . self::TABLE . '
         SET status = ?, processingStartedAt = ?, updatedAt = ?, attempts = attempts + 1
         WHERE id = ? AND status = ? AND deletedAt IS NULL',
        [
          OutboxDeliveryStatus::PROCESSING->value,
          $updatedAt,
          $updatedAt,
          $row['id'],
          OutboxDeliveryStatus::PENDING->value,
        ],
      );

      if ($statement === false || $statement->rowCount() !== 1) {
        continue;
      }

      $leased[] = $this->hydrateRecord($row, attempts: ((int) ($row['attempts'] ?? 0)) + 1, status: OutboxDeliveryStatus::PROCESSING, processingStartedAt: $effectiveNow);
    }

    return $leased;
  }

  public function markDispatched(string|int $id, ?DateTimeImmutable $dispatchedAt = null): void
  {
    $effectiveDispatchedAt = $dispatchedAt ?? new DateTimeImmutable();

    $this->repository->manager->query(
      'UPDATE ' . self::TABLE . '
       SET status = ?, dispatchedAt = ?, processingStartedAt = NULL, availableAt = NULL, lastError = NULL, updatedAt = ?
       WHERE id = ? AND deletedAt IS NULL',
      [
        OutboxDeliveryStatus::DISPATCHED->value,
        $effectiveDispatchedAt->format(self::DATE_TIME_FORMAT),
        $effectiveDispatchedAt->format(self::DATE_TIME_FORMAT),
        $id,
      ],
    );
  }

  public function markFailed(string|int $id, string|Throwable $error, ?DateTimeImmutable $retryAt = null): void
  {
    $effectiveRetryAt = $retryAt;
    $status = $effectiveRetryAt === null
      ? OutboxDeliveryStatus::FAILED
      : OutboxDeliveryStatus::PENDING;
    $updatedAt = new DateTimeImmutable();

    $this->repository->manager->query(
      'UPDATE ' . self::TABLE . '
       SET status = ?, availableAt = ?, processingStartedAt = NULL, lastError = ?, updatedAt = ?
       WHERE id = ? AND deletedAt IS NULL',
      [
        $status->value,
        $effectiveRetryAt?->format(self::DATE_TIME_FORMAT),
        $error instanceof Throwable ? $error->getMessage() : $error,
        $updatedAt->format(self::DATE_TIME_FORMAT),
        $id,
      ],
    );
  }

  private function hydrateRecord(
    array $row,
    ?int $attempts = null,
    ?OutboxDeliveryStatus $status = null,
    ?DateTimeImmutable $processingStartedAt = null,
  ): OutboxRecord {
    return new OutboxRecord(
      id: (int) $row['id'],
      eventName: (string) $row['eventName'],
      payload: $this->decodeJson((string) ($row['payloadJson'] ?? 'null')),
      headers: (array) $this->decodeJson((string) ($row['headersJson'] ?? '{}')),
      occurredAt: $this->parseDateTime($row['occurredAt'] ?? null) ?? new DateTimeImmutable(),
      availableAt: $this->parseDateTime($row['availableAt'] ?? null),
      attempts: $attempts ?? (int) ($row['attempts'] ?? 0),
      status: $status ?? OutboxDeliveryStatus::from((string) ($row['status'] ?? OutboxDeliveryStatus::PENDING->value)),
      processingStartedAt: $processingStartedAt ?? $this->parseDateTime($row['processingStartedAt'] ?? null),
      dispatchedAt: $this->parseDateTime($row['dispatchedAt'] ?? null),
      lastError: isset($row['lastError']) ? (string) $row['lastError'] : null,
    );
  }

  private function encodeJson(mixed $value): string
  {
    try {
      return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $exception) {
      throw new RuntimeException('Failed to encode an outbox payload as JSON.', previous: $exception);
    }
  }

  private function decodeJson(string $json): mixed
  {
    try {
      return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
      throw new RuntimeException('Failed to decode an outbox payload from JSON.', previous: $exception);
    }
  }

  private function parseDateTime(mixed $value): ?DateTimeImmutable
  {
    if (!is_string($value) || trim($value) === '') {
      return null;
    }

    return new DateTimeImmutable($value);
  }
}
