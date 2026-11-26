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

namespace Austral\LogBundle\Tests\Purge;

use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Event\ErrorsPurgedEvent;
use Austral\LogBundle\Event\LogEvents;
use Austral\LogBundle\Purge\ErrorPurger;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorLog;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorPurgerTest extends TestCase
{
  public function testTheOldResolvedAndArchivedErrorsAreDeletedWithTheirOccurrences(): void
  {
    $kit = new LogKit();
    $resolved = $this->error($kit, 'a', ErrorStatus::Resolved, '2026-08-01');
    $archived = $this->error($kit, 'b', ErrorStatus::Archived, '2026-08-01');
    $this->error($kit, 'c', ErrorStatus::Resolved, '2026-11-01');
    $this->error($kit, 'd', ErrorStatus::Ignored, '2026-01-01');
    $this->error($kit, 'e', ErrorStatus::InProgress, '2026-01-01');
    $this->error($kit, 'f', ErrorStatus::New, '2026-01-01');
    $kit->recorder->record($kit->record(['fingerprint' => $resolved->getFingerprint()]));
    $this->setStatus($kit, $resolved, ErrorStatus::Resolved, '2026-08-01');
    $events = [];
    $kit->dispatcher->addListener(LogEvents::PURGED, static function (ErrorsPurgedEvent $event) use (&$events): void {
      $events[] = $event;
    });

    self::assertSame(2, $kit->purger->count(90, ErrorPurger::PURGEABLE));
    self::assertSame(['errors' => 2, 'occurrences' => 3], $kit->purger->purge(90, ErrorPurger::PURGEABLE));

    $left = array_map(static fn (ErrorLogInterface $error): string => $error->getFingerprint()[0], $kit->errors()->findAll());
    sort($left);
    self::assertSame(['c', 'd', 'e', 'f'], $left, 'Recent, ignored, in progress and new errors are kept.');
    self::assertCount(4, $kit->occurrences());
    self::assertNull($kit->errors()->retrieveByFingerprint($archived->getFingerprint()));
    self::assertCount(1, $events);
    self::assertSame(2, $events[0]->errors);
    self::assertSame(3, $events[0]->occurrences);
    self::assertSame('2026-08-28', $events[0]->before->format('Y-m-d'));
  }

  public function testOnlyTheChosenStatuses(): void
  {
    $kit = new LogKit();
    $this->error($kit, 'a', ErrorStatus::Resolved, '2026-08-01');
    $this->error($kit, 'b', ErrorStatus::Archived, '2026-08-01');

    self::assertSame(['errors' => 1, 'occurrences' => 1], $kit->purger->purge(30, [ErrorStatus::Archived]));
    self::assertSame(ErrorStatus::Resolved, $kit->errors()->findAll()[0]->getStatus());
  }

  public function testAnErrorWithoutDateOfStatusIsDatedByItsLastOccurrence(): void
  {
    $kit = new LogKit();
    $error = $kit->error(['occurredAt' => new \DateTimeImmutable('2026-08-01')]);
    $kit->doctrine->getConnection()->update('austral_log_error', ['status' => 'resolved'], ['id' => $error->getId()]);

    self::assertSame(1, $kit->purger->count(90, ErrorPurger::PURGEABLE));
  }

  public function testNothingToPurge(): void
  {
    $kit = new LogKit();

    self::assertSame(0, $kit->purger->count(90, ErrorPurger::PURGEABLE));
    self::assertSame(['errors' => 0, 'occurrences' => 0], $kit->purger->purge(90, ErrorPurger::PURGEABLE));
  }

  public function testMoreErrorsThanABatch(): void
  {
    $kit = new LogKit();
    for ($i = 0; $i < 501; ++$i)
    {
      $kit->recorder->record($kit->record(['fingerprint' => hash('sha256', (string) $i), 'occurredAt' => new \DateTimeImmutable('2026-08-01')]));
    }
    $kit->doctrine->getConnection()->executeStatement("UPDATE austral_log_error SET status = 'archived'");

    self::assertSame(['errors' => 501, 'occurrences' => 501], $kit->purger->purge(90, ErrorPurger::PURGEABLE));
  }

  /**
   * @return iterable<string, array{list<ErrorStatus>}>
   */
  public static function neverPurged(): iterable
  {
    yield 'ignored' => [[ErrorStatus::Ignored]];
    yield 'new' => [[ErrorStatus::Resolved, ErrorStatus::New]];
    yield 'in progress' => [[ErrorStatus::InProgress]];
    yield 'none' => [[]];
  }

  /**
   * @param list<ErrorStatus> $statuses
   */
  #[DataProvider('neverPurged')]
  public function testTheOtherStatusesAreRefused(array $statuses): void
  {
    $this->expectException(\InvalidArgumentException::class);
    new LogKit()->purger->purge(90, $statuses);
  }

  public function testAtLeastOneDay(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    new LogKit()->purger->count(0, ErrorPurger::PURGEABLE);
  }

  private function error(LogKit $kit, string $letter, ErrorStatus $status, string $changedAt): ErrorLog
  {
    $error = $kit->error(['fingerprint' => str_repeat($letter, 64), 'occurredAt' => new \DateTimeImmutable($changedAt)]);
    $this->setStatus($kit, $error, $status, $changedAt);

    return $error;
  }

  private function setStatus(LogKit $kit, ErrorLog $error, ErrorStatus $status, string $changedAt): void
  {
    $kit->doctrine->getConnection()->update(
      'austral_log_error',
      ['status' => $status->value, 'status_changed_at' => new \DateTimeImmutable($changedAt)],
      ['id' => $error->getId()],
      ['status_changed_at' => 'datetime_immutable'],
    );
  }
}
