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

namespace Austral\LogBundle\Tests\Report;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Report\ErrorReport;
use Austral\LogBundle\Report\ErrorReporter;
use Austral\LogBundle\Tests\Fixtures\FakeMailer;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;

final class ErrorReporterTest extends TestCase
{
  public function testTheNewErrorsAreReportedOnce(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $reporter = $kit->reporter($mailer);
    $kit->error(['fingerprint' => str_repeat('a', 64), 'message' => 'First']);
    $kit->error(['fingerprint' => str_repeat('b', 64), 'message' => 'Second', 'level' => 500]);

    $report = $reporter->build();
    self::assertNotNull($report);
    self::assertSame(2, $report->total);
    self::assertNull($report->since, 'First report.');
    self::assertSame(['Second', 'First'], array_map(static fn (array $row): string => $row['message'], $report->getRows()), 'The highest level first.');
    self::assertSame(2, $reporter->send($report));

    self::assertCount(1, $mailer->sent);
    $email = $mailer->sent[0];
    self::assertSame('[prod] Errors of the site: 2 errors to check', $email->getSubject());
    self::assertSame(['dev@austral.dev', 'ops@austral.dev'], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
    self::assertSame('no-reply@austral.dev', $email->getFrom()[0]->getAddress());

    $kit->clear();
    self::assertNull($reporter->build(), 'Nothing new: nothing to send.');
    self::assertSame('2026-11-26 10:00:00', $kit->errors()->lastReportedAt()?->format('Y-m-d H:i:s'));
  }

  public function testOnlyTheNewErrorsAreReported(): void
  {
    $kit = new LogKit();
    $reporter = $kit->reporter(new FakeMailer());
    $kit->error(['fingerprint' => str_repeat('a', 64), 'message' => 'New']);
    foreach (['b' => ErrorStatus::Ignored, 'c' => ErrorStatus::InProgress, 'd' => ErrorStatus::Resolved, 'e' => ErrorStatus::Archived] as $letter => $status)
    {
      $kit->statuses->change($kit->error(['fingerprint' => str_repeat($letter, 64)]), $status);
    }

    $report = $reporter->build();
    self::assertNotNull($report);
    self::assertSame(1, $report->total);
    self::assertSame('New', $report->errors[0]->getMessage());
  }

  public function testARegressionAfterTheReportIsReportedAgain(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $reporter = $kit->reporter($mailer);
    $error = $kit->error();
    $reporter->send($this->report($reporter));
    $kit->statuses->change($error, ErrorStatus::Resolved);
    $kit->clear();

    $kit->clock->setNow('2026-11-27 09:00:00');
    $kit->recorder->record($kit->record(['occurredAt' => new \DateTimeImmutable('2026-11-27 08:00:00')]));

    $report = $reporter->build();
    self::assertNotNull($report);
    self::assertSame(1, $report->total);
    self::assertSame(1, $report->getRegressionCount());
    self::assertSame(0, $report->getNewCount());
    self::assertSame('2026-11-26 10:00:00', $report->since?->format('Y-m-d H:i:s'));
    self::assertTrue($report->getRows()[0]['regression']);
  }

  public function testTheErrorsBeyondTheLimitAreCountedAndMarkedToo(): void
  {
    $kit = new LogKit();
    $reporter = $kit->reporter(new FakeMailer(), limit: 2);
    foreach (['a', 'b', 'c'] as $letter)
    {
      $kit->error(['fingerprint' => str_repeat($letter, 64)]);
    }

    $report = $reporter->build();
    self::assertNotNull($report);
    self::assertCount(2, $report->errors);
    self::assertSame(3, $report->total);
    self::assertSame(1, $report->getMoreCount());
    self::assertSame(3, $reporter->send($report), 'Every error of the report is marked, not only the listed ones.');
    $kit->clear();
    self::assertNull($reporter->build());
  }

  public function testAnErrorAfterTheBuildIsKeptForTheNextReport(): void
  {
    $kit = new LogKit();
    $reporter = $kit->reporter(new FakeMailer());
    $kit->error(['fingerprint' => str_repeat('a', 64), 'occurredAt' => new \DateTimeImmutable('2026-11-26 09:00:00')]);
    $report = $this->report($reporter);

    $kit->error(['fingerprint' => str_repeat('b', 64), 'occurredAt' => new \DateTimeImmutable('2026-11-26 10:30:00')]);
    self::assertSame(1, $reporter->send($report));
    $kit->clear();

    $kit->clock->setNow('2026-11-26 11:00:00');
    self::assertSame(1, $reporter->build()?->total);
  }

  public function testNothingIsMarkedWhenTheEmailFails(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $mailer->fail = true;
    $reporter = $kit->reporter($mailer);
    $kit->error();

    try
    {
      $reporter->send($this->report($reporter));
      self::fail('The failure of the mailer is not hidden.');
    }
    catch (TransportException)
    {
    }
    $kit->clear();
    self::assertSame(1, $reporter->build()?->total, 'Sent again at the next run.');
  }

  public function testTheFrequency(): void
  {
    $kit = new LogKit();
    $reporter = $kit->reporter(new FakeMailer(), 'daily');
    self::assertTrue($reporter->isDue(), 'No report yet.');

    $kit->error();
    $reporter->send($this->report($reporter));
    $kit->clear();
    self::assertFalse($reporter->isDue());

    $kit->clock->setNow('2026-11-27 09:54:00');
    self::assertFalse($reporter->isDue());
    $kit->clock->setNow('2026-11-27 09:55:00');
    self::assertTrue($reporter->isDue(), 'A margin of five minutes for the cron.');

    $kit->clock->setNow('2026-11-26 10:55:00');
    self::assertTrue($kit->reporter(new FakeMailer(), 'hourly')->isDue());
    self::assertFalse($kit->reporter(new FakeMailer(), 'weekly')->isDue());
    $kit->clock->setNow('2026-12-03 09:55:00');
    self::assertTrue($kit->reporter(new FakeMailer(), 'weekly')->isDue());
  }

  public function testAnUnknownFrequencyIsRefused(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    new LogKit()->reporter(new FakeMailer(), 'monthly');
  }

  public function testTheTemplatesOfTheBundle(): void
  {
    self::assertSame('@AustralLog/email/report.html.twig', ErrorReporter::HTML_TEMPLATE);
    self::assertSame('@AustralLog/email/report.txt.twig', ErrorReporter::TEXT_TEMPLATE);
  }

  /**
   * The report to send: the test fails when there is none.
   */
  private function report(ErrorReporter $reporter): ErrorReport
  {
    return $reporter->build() ?? self::fail('No report.');
  }
}
