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

namespace Austral\LogBundle\Tests\Command;

use Austral\LogBundle\Command\PurgeCommand;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeCommandTest extends TestCase
{
  public function testThePurgeOfTheConfiguration(): void
  {
    $kit = $this->kit();
    $tester = new CommandTester(new PurgeCommand($kit->purger, 90, ['resolved', 'archived']));

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('2 error(s) resolved, archived for more than 90 day(s) deleted, with 2 occurrence(s).', $this->display($tester));
    self::assertCount(1, $kit->errors()->findAll());
  }

  public function testTheDryRunDeletesNothing(): void
  {
    $kit = $this->kit();
    $tester = new CommandTester(new PurgeCommand($kit->purger, 90, ['resolved', 'archived']));

    self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
    self::assertStringContainsString('2 error(s) resolved, archived for more than 90 day(s) would be deleted.', $this->display($tester));
    self::assertCount(3, $kit->errors()->findAll());
  }

  public function testTheOptionsReplaceTheConfiguration(): void
  {
    $kit = $this->kit();
    $tester = new CommandTester(new PurgeCommand($kit->purger, 0, ['resolved', 'archived']));

    self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '30', '--status' => ['archived']]));
    self::assertStringContainsString('1 error(s) archived for more than 30 day(s) deleted', $this->display($tester));
    self::assertCount(2, $kit->errors()->findAll());
  }

  public function testThePurgeIsDisabledWithZeroDays(): void
  {
    $kit = $this->kit();
    $tester = new CommandTester(new PurgeCommand($kit->purger, 0, ['resolved', 'archived']));

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('The purge is disabled', $this->display($tester));
    self::assertCount(3, $kit->errors()->findAll());
  }

  public function testTheInvalidOptions(): void
  {
    $kit = $this->kit();
    $tester = new CommandTester(new PurgeCommand($kit->purger, 90, ['resolved', 'archived']));

    self::assertSame(Command::INVALID, $tester->execute(['--status' => ['ignored']]));
    self::assertStringContainsString('"ignored" cannot be purged', $this->display($tester));
    self::assertSame(Command::INVALID, $tester->execute(['--days' => '0']));
    self::assertSame(Command::INVALID, $tester->execute(['--days' => 'ten']));
    self::assertCount(3, $kit->errors()->findAll());
  }

  /**
   * A resolved and an archived error of August, an ignored one of January.
   */
  private function kit(): LogKit
  {
    $kit = new LogKit();
    foreach (['a' => 'resolved', 'b' => 'archived', 'c' => 'ignored'] as $letter => $status)
    {
      $error = $kit->error(['fingerprint' => str_repeat($letter, 64), 'occurredAt' => new \DateTimeImmutable('2026-01-01')]);
      $kit->doctrine->getConnection()->update(
        'austral_log_error',
        ['status' => $status, 'status_changed_at' => new \DateTimeImmutable('c' === $letter ? '2026-01-01' : '2026-08-01')],
        ['id' => $error->getId()],
        ['status_changed_at' => 'datetime_immutable'],
      );
    }
    $kit->clear();

    return $kit;
  }

  private function display(CommandTester $tester): string
  {
    return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
  }
}
