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

use Austral\LogBundle\Command\ReportCommand;
use Austral\LogBundle\Tests\Fixtures\FakeMailer;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportCommandTest extends TestCase
{
  public function testTheReportIsSent(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $tester = new CommandTester(new ReportCommand($kit->reporter($mailer)));
    $kit->error();

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('Report sent: 1 error(s) reported.', $this->display($tester));
    self::assertCount(1, $mailer->sent);
  }

  public function testNothingNewNothingSent(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $tester = new CommandTester(new ReportCommand($kit->reporter($mailer)));

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('No new error to report', $this->display($tester));
    self::assertSame([], $mailer->sent);
  }

  public function testTheFrequencyIsRespectedUnlessForced(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $tester = new CommandTester(new ReportCommand($kit->reporter($mailer)));
    $kit->error(['fingerprint' => str_repeat('a', 64)]);
    $tester->execute([]);
    $kit->clear();
    $kit->error(['fingerprint' => str_repeat('b', 64)]);

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('The last report is too recent', $this->display($tester));
    self::assertCount(1, $mailer->sent);

    self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
    self::assertCount(2, $mailer->sent);
  }

  public function testTheDryRunSendsAndMarksNothing(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $tester = new CommandTester(new ReportCommand($kit->reporter($mailer, limit: 1)));
    $kit->error(['fingerprint' => str_repeat('a', 64), 'message' => 'First']);
    $kit->error(['fingerprint' => str_repeat('b', 64), 'message' => 'Second']);

    self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
    $display = $this->display($tester);
    self::assertStringContainsString('[prod] Errors of the site: 2 errors to check', $display);
    self::assertStringContainsString('RuntimeException: ', $display);
    self::assertStringContainsString('1 more error(s) not listed.', $display);
    self::assertStringContainsString('Dry run: nothing sent.', $display);
    self::assertSame([], $mailer->sent);
    $kit->clear();
    self::assertNull($kit->errors()->lastReportedAt());
  }

  private function display(CommandTester $tester): string
  {
    return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
  }
}
