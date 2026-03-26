<?php

namespace Assegai\Events\Assegai\Outbox\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Queries\Sql\ColumnType;
use Assegai\Orm\Traits\ChangeRecorderTrait;
use DateTime;

#[Entity(table: 'event_outbox')]
class OutboxMessageEntity
{
  use ChangeRecorderTrait;

  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false, lengthOrValues: 191)]
  public string $eventName = '';

  #[Column(type: ColumnType::LONGTEXT, nullable: false)]
  public string $payloadJson = '';

  #[Column(type: ColumnType::LONGTEXT, nullable: false)]
  public string $headersJson = '{}';

  #[Column(type: ColumnType::VARCHAR, nullable: false, lengthOrValues: 32, default: "'pending'")]
  public string $status = 'pending';

  #[Column(type: ColumnType::INT, nullable: false, default: 0)]
  public int $attempts = 0;

  #[Column(type: ColumnType::DATETIME, nullable: false)]
  public ?DateTime $occurredAt = null;

  #[Column(type: ColumnType::DATETIME, nullable: true)]
  public ?DateTime $availableAt = null;

  #[Column(type: ColumnType::DATETIME, nullable: true)]
  public ?DateTime $processingStartedAt = null;

  #[Column(type: ColumnType::DATETIME, nullable: true)]
  public ?DateTime $dispatchedAt = null;

  #[Column(type: ColumnType::LONGTEXT, nullable: true)]
  public ?string $lastError = null;
}
