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

use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Monolog\Level;

/**
 * Austral Error Report.
 *
 * The content of an email report: the errors listed (at most austral_log.report.limit), the number of errors to
 * report and the period covered.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final readonly class ErrorReport
{
  /**
   * ErrorReport constructor
   *
   * @param list<ErrorLogInterface> $errors      the errors listed
   * @param int                     $total       the errors to report, listed or not
   * @param \DateTimeImmutable|null $since       last report, null for the first one
   * @param \DateTimeImmutable      $until       date of this report
   * @param string                  $environment kernel.environment
   */
  public function __construct(
    public array $errors,
    public int $total,
    public ?\DateTimeImmutable $since,
    public \DateTimeImmutable $until,
    public string $environment,
  ) {
  }

  /**
   * getNewCount
   * Errors listed that were never reported.
   *
   * @return int
   */
  public function getNewCount(): int
  {
    return \count(array_filter($this->errors, static fn (ErrorLogInterface $error): bool => !self::isRegression($error)));
  }

  /**
   * getRegressionCount
   * Errors listed that came back after being resolved or archived.
   *
   * @return int
   */
  public function getRegressionCount(): int
  {
    return \count($this->errors) - $this->getNewCount();
  }

  /**
   * getMoreCount
   * Errors to report beyond the limit of the email.
   *
   * @return int
   */
  public function getMoreCount(): int
  {
    return max(0, $this->total - \count($this->errors));
  }

  /**
   * getRows
   * The errors listed, ready for the templates.
   *
   * @return list<array{id: string, regression: bool, level: string, class: ?string, message: string, file: ?string, line: ?int, channel: string, occurrences: int, firstSeen: \DateTimeImmutable, lastSeen: \DateTimeImmutable}>
   */
  public function getRows(): array
  {
    return array_map(static fn (ErrorLogInterface $error): array => [
      'id' => $error->getId(),
      'regression' => self::isRegression($error),
      'level' => self::levelName($error->getLevel()),
      'class' => $error->getExceptionClass(),
      'message' => $error->getMessage(),
      'file' => $error->getFile(),
      'line' => $error->getLine(),
      'channel' => $error->getChannel(),
      'occurrences' => $error->getOccurrences(),
      'firstSeen' => $error->getFirstSeen(),
      'lastSeen' => $error->getLastSeen(),
    ], $this->errors);
  }

  /**
   * isRegression
   *
   * @param ErrorLogInterface $error
   *
   * @return bool
   */
  private static function isRegression(ErrorLogInterface $error): bool
  {
    return null !== $error->getRegressedAt();
  }

  /**
   * levelName
   *
   * @param int $level Monolog\Level value
   *
   * @return string
   */
  private static function levelName(int $level): string
  {
    return Level::tryFrom($level)?->getName() ?? (string) $level;
  }
}
