<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Austral\LogBundle\Tests\Record;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorRecorderTest extends TestCase
{
  public function testAFirstOccurrenceCreatesTheError(): void
  {
    $kit = new LogKit();

    self::assertTrue($kit->recorder->record($kit->record([
      'url' => 'https://austral.dev/page',
      'method' => 'GET',
      'route' => 'page',
      'userId' => 'user-1',
      'trace' => [['class' => \RuntimeException::class, 'message' => 'Something went wrong', 'file' => 'src/Service.php', 'line' => 12, 'frames' => []]],
      'context' => ['context' => ['id' => 3]],
    ])));

    $error = $kit->errors()->retrieveByFingerprint(str_repeat('a', 64));
    self::assertNotNull($error);
    self::assertSame(ErrorStatus::New, $error->getStatus());
    self::assertSame(1, $error->getOccurrences());
    self::assertSame(400, $error->getLevel());
    self::assertSame('app', $error->getChannel());
    self::assertSame(\RuntimeException::class, $error->getExceptionClass());
    self::assertSame('Something went wrong', $error->getMessage());
    self::assertSame('src/Service.php', $error->getFile());
    self::assertSame(12, $error->getLine());
    self::assertSame('2026-11-26 10:00:00', $error->getFirstSeen()->format('Y-m-d H:i:s'));
    self::assertSame('2026-11-26 10:00:00', $error->getLastSeen()->format('Y-m-d H:i:s'));
    self::assertNull($error->getRegressedAt());

    $occurrences = $kit->occurrences();
    self::assertCount(1, $occurrences);
    $occurrence = $occurrences[0];
    self::assertSame($error->getId(), $occurrence->getError()->getId());
    self::assertSame('https://austral.dev/page', $occurrence->getUrl());
    self::assertSame('GET', $occurrence->getMethod());
    self::assertSame('page', $occurrence->getRoute());
    self::assertSame('user-1', $occurrence->getUserId());
    self::assertSame('prod', $occurrence->getEnvironment());
    self::assertSame(\RuntimeException::class, $occurrence->getTrace()[0]['class']);
    self::assertSame(['context' => ['id' => 3]], $occurrence->getContext());
  }

  public function testTheOccurrencesOfAnErrorAreGrouped(): void
  {
    $kit = new LogKit();

    $kit->recorder->record($kit->record());
    $kit->recorder->record($kit->record(['level' => 500, 'occurredAt' => new \DateTimeImmutable('2026-11-26 11:00:00')]));
    $kit->recorder->record($kit->record(['level' => 400, 'occurredAt' => new \DateTimeImmutable('2026-11-26 12:00:00')]));
    $kit->recorder->record($kit->record(['fingerprint' => str_repeat('b', 64)]));

    $error = $kit->errors()->retrieveByFingerprint(str_repeat('a', 64));
    self::assertNotNull($error);
    self::assertSame(3, $error->getOccurrences());
    self::assertSame(500, $error->getLevel(), 'The highest level is kept.');
    self::assertSame('2026-11-26 10:00:00', $error->getFirstSeen()->format('Y-m-d H:i:s'));
    self::assertSame('2026-11-26 12:00:00', $error->getLastSeen()->format('Y-m-d H:i:s'));
    self::assertCount(3, $error->getOccurrenceList());
    self::assertCount(2, $kit->errors()->findAll());
    self::assertCount(4, $kit->occurrences());
  }

  /**
   * @return iterable<string, array{ErrorStatus, ErrorStatus, bool}>
   */
  public static function recurrences(): iterable
  {
    yield 'new' => [ErrorStatus::New, ErrorStatus::New, false];
    yield 'in progress' => [ErrorStatus::InProgress, ErrorStatus::InProgress, false];
    yield 'resolved' => [ErrorStatus::Resolved, ErrorStatus::New, true];
    yield 'archived' => [ErrorStatus::Archived, ErrorStatus::New, true];
    yield 'ignored' => [ErrorStatus::Ignored, ErrorStatus::Ignored, false];
  }

  #[DataProvider('recurrences')]
  public function testTheStatusWhenTheErrorComesBack(ErrorStatus $status, ErrorStatus $expected, bool $regression): void
  {
    $kit = new LogKit();
    $kit->recorder->record($kit->record());
    $kit->doctrine->getConnection()->update('austral_log_error', ['status' => $status->value], ['fingerprint' => str_repeat('a', 64)]);

    $kit->recorder->record($kit->record(['occurredAt' => new \DateTimeImmutable('2026-11-27 08:00:00')]));

    $error = $kit->errors()->retrieveByFingerprint(str_repeat('a', 64));
    self::assertNotNull($error);
    self::assertSame($expected, $error->getStatus());
    self::assertSame($expected, $status->afterRecurrence(), 'The SQL and the enum give the same status.');
    self::assertSame($regression ? '2026-11-27 08:00:00' : null, $error->getRegressedAt()?->format('Y-m-d H:i:s'));
    self::assertSame(2, $error->getOccurrences());
  }

  public function testOnlyTheLastOccurrencesAreKept(): void
  {
    $kit = new LogKit(occurrencesLimit: 3);
    $kit->recorder->record($kit->record(['fingerprint' => str_repeat('b', 64)]));
    for ($i = 1; $i <= 5; ++$i)
    {
      $kit->recorder->record($kit->record(['message' => "occurrence {$i}"]));
    }

    $error = $kit->errors()->retrieveByFingerprint(str_repeat('a', 64));
    self::assertNotNull($error);
    self::assertSame(5, $error->getOccurrences(), 'The counter keeps the purged occurrences.');
    self::assertSame(['occurrence 5', 'occurrence 4', 'occurrence 3'], array_map(
      static fn ($occurrence): string => $occurrence->getMessage(),
      $error->getOccurrenceList()->toArray(),
    ));
    self::assertCount(4, $kit->occurrences(), 'The occurrences of the other errors are not purged.');
  }

  public function testTheErrorIsSavedWhenTheTransactionOfTheApplicationIsRolledBack(): void
  {
    $kit = new LogKit();
    $connection = $kit->doctrine->getConnection();
    $connection->beginTransaction();

    self::assertTrue($kit->recorder->record($kit->record()));
    $connection->rollBack();

    self::assertNotNull($kit->errors()->retrieveByFingerprint(str_repeat('a', 64)));
  }

  public function testTheRecorderNeverThrowsAndStopsUntilReset(): void
  {
    $kit = new LogKit(withSchema: false);

    self::assertFalse($kit->recorder->record($kit->record()));
    self::assertNotNull($kit->recorder->getLastFailure(), 'The reason is kept (here: no table).');

    new SchemaTool($kit->doctrine)->createSchema($kit->doctrine->getMetadataFactory()->getAllMetadata());
    self::assertFalse($kit->recorder->record($kit->record()), 'Stopped: a broken database does not slow down each error.');

    $kit->recorder->reset();
    self::assertNull($kit->recorder->getLastFailure());
    self::assertTrue($kit->recorder->record($kit->record()));
    self::assertCount(1, $kit->occurrences());
  }
}
