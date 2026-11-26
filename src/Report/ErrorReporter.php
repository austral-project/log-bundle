<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Report;

use Austral\LogBundle\Repository\ErrorLogRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Austral Error Reporter.
 *
 * Builds and sends the email report of the errors (austral:log:report), never one email by error:
 *   - isDue(): the frequency (hourly, daily, weekly) has passed since the last report;
 *   - build(): the new errors and the regressions since the last report, null when there is nothing to report;
 *   - send(): renders the templates, sends the email, then marks the errors as reported.
 *
 * The errors are marked only once the email is sent: a failed sending is retried at the next run. A margin of five
 * minutes is left on the frequency, so a command run every hour by cron sends an hourly report at each run.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorReporter
{
  public const array FREQUENCIES = [
    'hourly' => 'PT1H',
    'daily' => 'P1D',
    'weekly' => 'P7D',
  ];

  public const string HTML_TEMPLATE = '@AustralLog/email/report.html.twig';

  public const string TEXT_TEMPLATE = '@AustralLog/email/report.txt.twig';

  private const string MARGIN = 'PT5M';

  /**
   * ErrorReporter constructor
   *
   * @param ManagerRegistry     $doctrine
   * @param MailerInterface     $mailer
   * @param Environment         $twig
   * @param class-string        $errorClass
   * @param string              $frequency   hourly, daily or weekly
   * @param list<string>        $recipients
   * @param string              $from
   * @param string              $subject
   * @param int                 $limit       errors listed in the email
   * @param string              $environment kernel.environment
   * @param ClockInterface|null $clock
   */
  public function __construct(
    private readonly ManagerRegistry $doctrine,
    private readonly MailerInterface $mailer,
    private readonly Environment $twig,
    private readonly string $errorClass,
    private readonly string $frequency,
    private readonly array $recipients,
    private readonly string $from,
    private readonly string $subject,
    private readonly int $limit,
    private readonly string $environment,
    private readonly ?ClockInterface $clock = null,
  ) {
    if (!isset(self::FREQUENCIES[$frequency]))
    {
      throw new \InvalidArgumentException(\sprintf('Unknown frequency "%s": hourly, daily or weekly.', $frequency));
    }
  }

  /**
   * isDue
   * True when no report was sent yet, or when the frequency has passed since the last one.
   *
   * @return bool
   */
  public function isDue(): bool
  {
    $last = $this->errors()->lastReportedAt();
    if (null === $last)
    {
      return true;
    }
    $next = $last->add(new \DateInterval(self::FREQUENCIES[$this->frequency]))->sub(new \DateInterval(self::MARGIN));

    return $this->now() >= $next;
  }

  /**
   * build
   * The report of the errors to report now, null when there is none.
   *
   * @return ErrorReport|null
   */
  public function build(): ?ErrorReport
  {
    $until = $this->now();
    $total = $this->errors()->countToReport($until);
    if (0 === $total)
    {
      return null;
    }

    return new ErrorReport($this->errors()->selectToReport($until, $this->limit), $total, $this->errors()->lastReportedAt(), $until, $this->environment);
  }

  /**
   * send
   * Sends the report, then marks its errors (those beyond the limit too) as reported.
   *
   * @param ErrorReport $report
   *
   * @return int number of errors marked as reported
   */
  public function send(ErrorReport $report): int
  {
    $context = ['report' => $report, 'subject' => $this->subjectOf($report)];
    $email = new Email()
      ->from(Address::create($this->from))
      ->to(...array_map(static fn (string $recipient): Address => Address::create($recipient), $this->recipients))
      ->subject($this->subjectOf($report))
      ->html($this->twig->render(self::HTML_TEMPLATE, $context))
      ->text($this->twig->render(self::TEXT_TEMPLATE, $context));
    $this->mailer->send($email);

    return $this->errors()->markReported($report->until, $this->now());
  }

  /**
   * subjectOf
   * "[prod] Errors of the site: 4 errors to check".
   *
   * @param ErrorReport $report
   *
   * @return string
   */
  public function subjectOf(ErrorReport $report): string
  {
    return \sprintf('[%s] %s: %d %s to check', $report->environment, $this->subject, $report->total, 1 === $report->total ? 'error' : 'errors');
  }

  /**
   * errors
   *
   * @return ErrorLogRepository
   */
  private function errors(): ErrorLogRepository
  {
    $repository = $this->doctrine->getManagerForClass($this->errorClass)?->getRepository($this->errorClass);
    if (!$repository instanceof ErrorLogRepository)
    {
      throw new \LogicException(\sprintf('The repository of "%s" must be "%s".', $this->errorClass, ErrorLogRepository::class));
    }

    return $repository;
  }

  /**
   * now
   *
   * @return \DateTimeImmutable
   */
  private function now(): \DateTimeImmutable
  {
    return $this->clock?->now() ?? new \DateTimeImmutable();
  }
}
