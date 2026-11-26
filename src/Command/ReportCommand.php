<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Command;

use Austral\LogBundle\Report\ErrorReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Austral Report Command: austral:log:report.
 *
 * Sends the email report of the new errors and of the regressions, at the frequency of
 * "austral_log.report.frequency". To run every hour (cron or Symfony Scheduler), whatever the frequency:
 *
 *   php bin/console austral:log:report
 *   php bin/console austral:log:report --force     # now, whatever the frequency
 *   php bin/console austral:log:report --dry-run   # lists the errors, sends nothing
 *
 * Only defined when "austral_log.report.enabled" is true.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class ReportCommand extends Command
{
  /**
   * ReportCommand constructor
   *
   * @param ErrorReporter $reporter
   */
  public function __construct(
    private readonly ErrorReporter $reporter,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure(): void
  {
    $this
      ->setDescription('Sends the email report of the new errors and of the regressions')
      ->addOption('force', null, InputOption::VALUE_NONE, 'Sends now, whatever the frequency')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists the errors of the report, sends nothing');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $dryRun = true === $input->getOption('dry-run');

    if (!$dryRun && true !== $input->getOption('force') && !$this->reporter->isDue())
    {
      $io->note('The last report is too recent for the frequency (austral_log.report.frequency): nothing sent.');

      return Command::SUCCESS;
    }
    $report = $this->reporter->build();
    if (null === $report)
    {
      $io->success('No new error to report: nothing sent.');

      return Command::SUCCESS;
    }

    if ($dryRun)
    {
      $io->title($this->reporter->subjectOf($report));
      $io->table(['Status', 'Level', 'Error', 'Occurrences', 'Last seen'], array_map(static fn (array $row): array => [
        $row['regression'] ? 'Regression' : 'New',
        $row['level'],
        ($row['class'] ? $row['class'].': ' : '').mb_strimwidth($row['message'], 0, 80, '…'),
        $row['occurrences'],
        $row['lastSeen']->format('Y-m-d H:i'),
      ], $report->getRows()));
      if ($report->getMoreCount() > 0)
      {
        $io->text(\sprintf('%d more error(s) not listed.', $report->getMoreCount()));
      }
      $io->note('Dry run: nothing sent.');

      return Command::SUCCESS;
    }

    $marked = $this->reporter->send($report);
    $io->success(\sprintf('Report sent: %d error(s) reported.', $marked));

    return Command::SUCCESS;
  }
}
