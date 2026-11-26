<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Record;

use Austral\LogBundle\Enum\ErrorStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Austral Error Recorder.
 *
 * Saves an ErrorRecord with plain SQL on its own connection (austral.log.connection), out of the transaction of the
 * application and of the unit of work of the ORM:
 *   1. the error with the same fingerprint is updated (counter, last date, highest level, regression);
 *   2. if there is none, it is inserted (two processes that insert the same error at the same time: the second
 *      one updates it);
 *   3. the occurrence is inserted, and the occurrences beyond the limit are deleted.
 *
 * The connection and Doctrine are given as closures (!service_closure): the logger is built before them, and the
 * logging middleware of Doctrine needs the logger.
 *
 * The recorder never throws: when the database does not answer, nothing is saved (the log file of Symfony keeps
 * the error) and the recorder stops trying until reset(), so a broken database does not slow down each error.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorRecorder implements ResetInterface
{
  /**
   * @var array{error: string, occurrence: string}|null
   */
  private ?array $tables = null;

  /**
   * @var bool
   */
  private bool $disabled = false;

  /**
   * @var \Throwable|null
   */
  private ?\Throwable $lastFailure = null;

  /**
   * @var Connection|null
   */
  private ?Connection $connection = null;

  /**
   * ErrorRecorder constructor
   *
   * @param \Closure(): Connection      $connectionFactory austral.log.connection
   * @param \Closure(): ManagerRegistry $doctrineFactory   doctrine
   * @param string                      $errorClass
   * @param string                      $occurrenceClass
   * @param int                         $occurrencesLimit
   */
  public function __construct(
    private readonly \Closure $connectionFactory,
    private readonly \Closure $doctrineFactory,
    private readonly string $errorClass,
    private readonly string $occurrenceClass,
    private readonly int $occurrencesLimit,
  ) {
  }

  /**
   * record
   *
   * @param ErrorRecord $record
   *
   * @return bool false when nothing was saved (see getLastFailure())
   */
  public function record(ErrorRecord $record): bool
  {
    if ($this->disabled)
    {
      return false;
    }
    try
    {
      $this->write($record);

      return true;
    }
    catch (\Throwable $exception)
    {
      $this->disabled = true;
      $this->lastFailure = $exception;

      return false;
    }
  }

  /**
   * getLastFailure
   * Why the last record was not saved (missing table, database down...).
   *
   * @return \Throwable|null
   */
  public function getLastFailure(): ?\Throwable
  {
    return $this->lastFailure;
  }

  /**
   * {@inheritDoc}
   */
  public function reset(): void
  {
    // The workers (Messenger) try again for the next message.
    $this->disabled = false;
    $this->lastFailure = null;
  }

  /**
   * write
   *
   * @param ErrorRecord $record
   *
   * @return void
   */
  private function write(ErrorRecord $record): void
  {
    $tables = $this->tables();
    $error = $this->update($tables['error'], $record);
    if (null === $error)
    {
      try
      {
        $error = $this->insert($tables['error'], $record);
      }
      catch (UniqueConstraintViolationException)
      {
        $error = $this->update($tables['error'], $record) ?? throw new \LogicException('The error inserted by another process was not found.');
      }
    }

    $this->connection()->insert($tables['occurrence'], [
      'id' => Uuid::v7()->toRfc4122(),
      'error_id' => $error['id'],
      'occurred_at' => $record->occurredAt,
      'level' => $record->level,
      'message' => $record->message,
      'url' => $record->url,
      'method' => $record->method,
      'route' => $record->route,
      'user_id' => $record->userId,
      'environment' => $record->environment,
      'trace' => $record->trace,
      'context' => $record->context,
    ], [
      'occurred_at' => Types::DATETIME_IMMUTABLE,
      'level' => ParameterType::INTEGER,
      'trace' => Types::JSON,
      'context' => Types::JSON,
    ]);

    if ($error['occurrences'] > $this->occurrencesLimit)
    {
      $this->purgeOccurrences($tables['occurrence'], $error['id']);
    }
  }

  /**
   * update
   * One more occurrence of the error, if it exists. A regression changes the status and its date (no event: the
   * recorder writes in SQL, from the logger). The dates are set before the status: MySQL reads the
   * columns already changed by the same UPDATE, PostgreSQL and SQLite read the old values; this order gives the
   * same result everywhere.
   *
   * @param string      $table
   * @param ErrorRecord $record
   *
   * @return array{id: string, occurrences: int}|null
   */
  private function update(string $table, ErrorRecord $record): ?array
  {
    $updated = $this->connection()->executeStatement(
      "UPDATE {$table} SET
        occurrences = occurrences + 1,
        last_seen = :now,
        level = CASE WHEN level < :level THEN :level ELSE level END,
        regressed_at = CASE WHEN status IN (:regressions) THEN :now ELSE regressed_at END,
        status_changed_at = CASE WHEN status IN (:regressions) THEN :now ELSE status_changed_at END,
        status = CASE WHEN status IN (:regressions) THEN :new ELSE status END
      WHERE fingerprint = :fingerprint",
      [
        'now' => $record->occurredAt,
        'level' => $record->level,
        'regressions' => array_map(static fn (ErrorStatus $status): string => $status->value, ErrorStatus::regressions()),
        'new' => ErrorStatus::New->value,
        'fingerprint' => $record->fingerprint,
      ],
      [
        'now' => Types::DATETIME_IMMUTABLE,
        'level' => ParameterType::INTEGER,
        'regressions' => ArrayParameterType::STRING,
      ],
    );
    if (0 === $updated)
    {
      return null;
    }
    $row = $this->connection()->fetchAssociative("SELECT id, occurrences FROM {$table} WHERE fingerprint = :fingerprint", ['fingerprint' => $record->fingerprint]);

    return false === $row ? null : ['id' => (string) $row['id'], 'occurrences' => (int) $row['occurrences']];
  }

  /**
   * insert
   *
   * @param string      $table
   * @param ErrorRecord $record
   *
   * @return array{id: string, occurrences: int}
   */
  private function insert(string $table, ErrorRecord $record): array
  {
    $id = Uuid::v7()->toRfc4122();
    $this->connection()->insert($table, [
      'id' => $id,
      'fingerprint' => $record->fingerprint,
      'level' => $record->level,
      'channel' => $record->channel,
      'exception_class' => $record->exceptionClass,
      'message' => $record->message,
      'file' => $record->file,
      'line' => $record->line,
      'status' => ErrorStatus::New->value,
      'occurrences' => 1,
      'first_seen' => $record->occurredAt,
      'last_seen' => $record->occurredAt,
    ], [
      'level' => ParameterType::INTEGER,
      'line' => ParameterType::INTEGER,
      'occurrences' => ParameterType::INTEGER,
      'first_seen' => Types::DATETIME_IMMUTABLE,
      'last_seen' => Types::DATETIME_IMMUTABLE,
    ]);

    return ['id' => $id, 'occurrences' => 1];
  }

  /**
   * purgeOccurrences
   * Only the last occurrences are kept: the ids are UUID v7, in the order of the occurrences.
   *
   * @param string $table
   * @param string $errorId
   *
   * @return void
   */
  private function purgeOccurrences(string $table, string $errorId): void
  {
    $platform = $this->connection()->getDatabasePlatform();
    $oldestKept = $this->connection()->fetchOne(
      $platform->modifyLimitQuery("SELECT id FROM {$table} WHERE error_id = :error ORDER BY id DESC", 1, $this->occurrencesLimit - 1),
      ['error' => $errorId],
    );
    if (\is_string($oldestKept))
    {
      $this->connection()->executeStatement("DELETE FROM {$table} WHERE error_id = :error AND id < :oldest", ['error' => $errorId, 'oldest' => $oldestKept]);
    }
  }

  /**
   * tables
   * The tables of the entities of the application, read once from their mapping.
   *
   * @return array{error: string, occurrence: string}
   */
  private function tables(): array
  {
    return $this->tables ??= [
      'error' => $this->table($this->errorClass),
      'occurrence' => $this->table($this->occurrenceClass),
    ];
  }

  /**
   * table
   *
   * @param string $class
   *
   * @return string
   */
  private function table(string $class): string
  {
    $manager = class_exists($class) ? ($this->doctrineFactory)()->getManagerForClass($class) : null;
    if (!$manager instanceof EntityManagerInterface)
    {
      throw new \LogicException(\sprintf('The entity "%s" of log-bundle is not mapped by Doctrine.', $class));
    }

    return $manager->getConfiguration()->getQuoteStrategy()->getTableName($manager->getClassMetadata($class), $this->connection()->getDatabasePlatform());
  }

  /**
   * connection
   *
   * @return Connection
   */
  private function connection(): Connection
  {
    return $this->connection ??= ($this->connectionFactory)();
  }
}
