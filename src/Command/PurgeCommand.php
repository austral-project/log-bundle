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

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Purge\ErrorPurger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Austral Purge Command: austral:log:purge.
 *
 * Deletes the resolved and archived errors whose status was changed more than "austral_log.purge.after_days"
 * days ago. To run every day (cron or Symfony Scheduler):
 *
 *   php bin/console austral:log:purge
 *   php bin/console austral:log:purge --days=30 --status=archived --dry-run
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class PurgeCommand extends Command
{
  /**
   * PurgeCommand constructor
   *
   * @param ErrorPurger  $purger
   * @param int          $afterDays 0: no purge without --days
   * @param list<string> $statuses
   */
  public function __construct(
    private readonly ErrorPurger $purger,
    private readonly int $afterDays,
    private readonly array $statuses,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure(): void
  {
    $this
      ->setDescription('Deletes the resolved and archived errors, with their occurrences')
      ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Days since the change of the status (default: austral_log.purge.after_days)')
      ->addOption('status', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Statuses purged: resolved, archived (default: austral_log.purge.statuses)')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Counts the errors to delete, deletes nothing');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $option = $input->getOption('days');
    if (null !== $option && (!\is_string($option) || 1 !== preg_match('/^[1-9]\d*$/D', $option)))
    {
      $io->error('--days must be a number of days, 1 or more.');

      return Command::INVALID;
    }
    $days = null === $option ? $this->afterDays : (int) $option;
    if (0 === $days)
    {
      $io->note('The purge is disabled (austral_log.purge.after_days: 0).');

      return Command::SUCCESS;
    }

    $names = $input->getOption('status');
    $names = \is_array($names) && [] !== $names ? $names : $this->statuses;
    $statuses = [];
    foreach ($names as $name)
    {
      $status = \is_string($name) ? ErrorStatus::tryFrom($name) : null;
      if (null === $status || !\in_array($status, ErrorPurger::PURGEABLE, true))
      {
        $io->error(\sprintf('"%s" cannot be purged: only resolved and archived.', \is_string($name) ? $name : get_debug_type($name)));

        return Command::INVALID;
      }
      $statuses[] = $status;
    }
    $label = implode(', ', array_map(static fn (ErrorStatus $status): string => $status->value, $statuses));

    if ($input->getOption('dry-run'))
    {
      $io->success(\sprintf('%d error(s) %s for more than %d day(s) would be deleted.', $this->purger->count($days, $statuses), $label, $days));

      return Command::SUCCESS;
    }
    $deleted = $this->purger->purge($days, $statuses);
    $io->success(\sprintf('%d error(s) %s for more than %d day(s) deleted, with %d occurrence(s).', $deleted['errors'], $label, $days, $deleted['occurrences']));

    return Command::SUCCESS;
  }
}
