<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Purge;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Event\ErrorsPurgedEvent;
use Austral\LogBundle\Event\LogEvents;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Austral Error Purger.
 *
 * Deletes the resolved and archived errors whose status was changed more than X days ago, with their occurrences.
 * The other statuses are never purged: a new or in progress error is not handled yet, and an ignored error must
 * stay ignored (purged, it would come back as new).
 *
 * The errors are deleted by batches of ids; the occurrences are deleted first, explicitly (the cascade of the
 * foreign key is not enabled on every database, SQLite for instance). The status is checked again when an error
 * is deleted: an error that came back in the meantime is kept.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorPurger
{
  public const array PURGEABLE = [ErrorStatus::Resolved, ErrorStatus::Archived];

  private const int BATCH = 500;

  /**
   * ErrorPurger constructor
   *
   * @param ManagerRegistry          $doctrine
   * @param EventDispatcherInterface $dispatcher
   * @param string                   $errorClass
   * @param string                   $occurrenceClass
   * @param ClockInterface|null      $clock
   */
  public function __construct(
    private readonly ManagerRegistry $doctrine,
    private readonly EventDispatcherInterface $dispatcher,
    private readonly string $errorClass,
    private readonly string $occurrenceClass,
    private readonly ?ClockInterface $clock = null,
  ) {
  }

  /**
   * count
   * The errors that purge() would delete.
   *
   * @param int               $afterDays
   * @param list<ErrorStatus> $statuses
   *
   * @return int
   */
  public function count(int $afterDays, array $statuses): int
  {
    $builder = $this->purgeable($this->before($afterDays), $statuses)->select('COUNT(error.id)');

    return (int) $builder->getQuery()->getSingleScalarResult();
  }

  /**
   * purge
   *
   * @param int               $afterDays days since the status was changed (at least 1)
   * @param list<ErrorStatus> $statuses  resolved and/or archived
   *
   * @return array{errors: int, occurrences: int}
   */
  public function purge(int $afterDays, array $statuses): array
  {
    $before = $this->before($afterDays);
    $manager = $this->manager();
    $deleted = ['errors' => 0, 'occurrences' => 0];
    do
    {
      $ids = array_column($this->purgeable($before, $statuses)->select('error.id')->setMaxResults(self::BATCH)->getQuery()->getScalarResult(), 'id');
      if ([] === $ids)
      {
        break;
      }
      $manager->wrapInTransaction(function (EntityManagerInterface $manager) use ($ids, $before, $statuses, &$deleted): void {
        $deleted['occurrences'] += (int) $manager->createQueryBuilder()
          ->delete($this->occurrenceClass, 'occurrence')
          ->where('IDENTITY(occurrence.error) IN (:ids)')
          ->setParameter('ids', $ids)
          ->getQuery()
          ->execute();
        $deleted['errors'] += (int) $this->purgeable($before, $statuses, $manager->createQueryBuilder()->delete($this->errorClass, 'error'))
          ->andWhere('error.id IN (:ids)')
          ->setParameter('ids', $ids)
          ->getQuery()
          ->execute();
      });
    }
    while (self::BATCH === \count($ids));
    $manager->clear();

    $this->dispatcher->dispatch(new ErrorsPurgedEvent($deleted['errors'], $deleted['occurrences'], $before, $statuses), LogEvents::PURGED);

    return $deleted;
  }

  /**
   * purgeable
   *
   * @param \DateTimeImmutable $before
   * @param list<ErrorStatus>  $statuses
   * @param QueryBuilder|null  $builder
   *
   * @return QueryBuilder
   */
  private function purgeable(\DateTimeImmutable $before, array $statuses, ?QueryBuilder $builder = null): QueryBuilder
  {
    foreach ($statuses as $status)
    {
      if (!\in_array($status, self::PURGEABLE, true))
      {
        throw new \InvalidArgumentException(\sprintf('The errors "%s" are never purged: only resolved and archived ones.', $status->value));
      }
    }
    if ([] === $statuses)
    {
      throw new \InvalidArgumentException('No status to purge.');
    }
    $builder ??= $this->manager()->createQueryBuilder()->from($this->errorClass, 'error');

    // An error whose status has no date (changed by hand in the database) is dated by its last occurrence.
    return $builder
      ->where('error.status IN (:statuses)')
      ->andWhere('COALESCE(error.statusChangedAt, error.lastSeen) < :before')
      ->setParameter('statuses', array_map(static fn (ErrorStatus $status): string => $status->value, $statuses))
      ->setParameter('before', $before, 'datetime_immutable');
  }

  /**
   * before
   *
   * @param int $afterDays
   *
   * @return \DateTimeImmutable
   */
  private function before(int $afterDays): \DateTimeImmutable
  {
    if ($afterDays < 1)
    {
      throw new \InvalidArgumentException('The errors are purged at least 1 day after the change of their status.');
    }

    return ($this->clock?->now() ?? new \DateTimeImmutable())->modify("-{$afterDays} days");
  }

  /**
   * manager
   *
   * @return EntityManagerInterface
   */
  private function manager(): EntityManagerInterface
  {
    $manager = $this->doctrine->getManagerForClass($this->errorClass);
    if (!$manager instanceof EntityManagerInterface)
    {
      throw new \LogicException(\sprintf('The entity "%s" of log-bundle is not mapped by Doctrine.', $this->errorClass));
    }

    return $manager;
  }
}
