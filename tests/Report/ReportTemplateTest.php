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

use Austral\LogBundle\Report\ErrorReport;
use Austral\LogBundle\Report\ErrorReporter;
use Austral\LogBundle\Tests\Fixtures\FakeMailer;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\TestCase;

/**
 * The content of the email, rendered by the templates of the bundle.
 */
final class ReportTemplateTest extends TestCase
{
  public function testTheEmail(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $reporter = $kit->reporter($mailer, limit: 1);
    $kit->error(['fingerprint' => str_repeat('a', 64), 'message' => 'Order <script>alert(1)</script> failed', 'level' => 500, 'channel' => 'request']);
    $kit->error(['fingerprint' => str_repeat('b', 64), 'message' => 'Not listed']);

    $reporter->send($this->report($reporter));

    $email = $mailer->sent[0];
    $html = (string) $email->getHtmlBody();
    $text = (string) $email->getTextBody();
    foreach ([$html, $text] as $body)
    {
      self::assertStringContainsString('[prod] Errors of the site: 2 errors to check', $body);
      self::assertStringContainsString('First report, until 2026-11-26 10:00', $body);
      self::assertStringContainsString('1 new, 0 regressions, and 1 more not listed', $body);
      self::assertStringContainsString('CRITICAL', $body);
      self::assertStringContainsString('RuntimeException', $body);
      self::assertStringContainsString('src/Service.php:12', $body);
      self::assertStringContainsString('1 more error is not listed', $body);
      self::assertStringNotContainsString('Not listed', $body);
    }
    self::assertStringContainsString('Order &lt;script&gt;alert(1)&lt;/script&gt; failed', $html, 'The messages are escaped in the HTML.');
    self::assertStringNotContainsString('<script>', $html);
    self::assertStringContainsString('Order <script>alert(1)</script> failed', $text, 'Not in the text version.');
  }

  public function testALongMessageIsCut(): void
  {
    $kit = new LogKit();
    $mailer = new FakeMailer();
    $reporter = $kit->reporter($mailer);
    $kit->error(['message' => str_repeat('x', 400)]);

    $reporter->send($this->report($reporter));

    self::assertStringContainsString(str_repeat('x', 300).'…', (string) $mailer->sent[0]->getTextBody());
    self::assertStringNotContainsString(str_repeat('x', 301), (string) $mailer->sent[0]->getHtmlBody());
  }

  /**
   * The report to send: the test fails when there is none.
   */
  private function report(ErrorReporter $reporter): ErrorReport
  {
    return $reporter->build() ?? self::fail('No report.');
  }
}
