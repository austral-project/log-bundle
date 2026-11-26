<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Repository;

use Austral\EntityBundle\Repository\EntityRepository;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Enum\ErrorStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * Austral Error Log Repository.
 *
 * The errors to report (austral:log:report) are the new errors not reported yet: new ones, never listed by a
 * report, and regressions that came back after the last report that listed them. An error ignored, in progress,
 * resolved or archived before the report is not reported.
 *
 * @extends EntityRepository<ErrorLogInterface>
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class ErrorLogRepository extends EntityRepository
{
  /**
   * retrieveByFingerprint
   *
   * @param string $fingerprint
   *
   * @return ErrorLogInterface|null
   */
  public function retrieveByFingerprint(string $fingerprint): ?ErrorLogInterface
  {
    $error = $this->createQueryBuilder('root')
      ->where('root.fingerprint = :fingerprint')
      ->setParameter('fingerprint', $fingerprint)
      ->getQuery()
      ->getOneOrNullResult();

    return $error instanceof ErrorLogInterface ? $error : null;
  }

  /**
   * selectToReport
   * The errors to report, the highest level first, then the last seen.
   *
   * @param \DateTimeImmutable $until occurrences up to this date
   * @param int                $limit
   *
   * @return list<ErrorLogInterface>
   */
  public function selectToReport(\DateTimeImmutable $until, int $limit): array
  {
    /** @var list<ErrorLogInterface> $errors */
    $errors = $this->toReport($until)
      ->orderBy('root.level', 'DESC')
      ->addOrderBy('root.lastSeen', 'DESC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return $errors;
  }

  /**
   * countToReport
   *
   * @param \DateTimeImmutable $until occurrences up to this date
   *
   * @return int
   */
  public function countToReport(\DateTimeImmutable $until): int
  {
    return (int) $this->toReport($until)->select('COUNT(root.id)')->getQuery()->getSingleScalarResult();
  }

  /**
   * markReported
   * The errors to report are marked as reported at this date.
   *
   * @param \DateTimeImmutable $until      occurrences up to this date, as for selectToReport()
   * @param \DateTimeImmutable $reportedAt
   *
   * @return int number of errors marked
   */
  public function markReported(\DateTimeImmutable $until, \DateTimeImmutable $reportedAt): int
  {
    $builder = $this->getEntityManager()->createQueryBuilder()
      ->update($this->getEntityName(), 'root')
      ->set('root.notifiedAt', ':reportedAt')
      ->setParameter('reportedAt', $reportedAt, Types::DATETIME_IMMUTABLE);

    return (int) $this->criteriaToReport($builder, $until)->getQuery()->execute();
  }

  /**
   * lastReportedAt
   * Date of the last report, null when no error was ever reported (or when they were all purged).
   *
   * @return \DateTimeImmutable|null
   */
  public function lastReportedAt(): ?\DateTimeImmutable
  {
    $last = $this->createQueryBuilder('root')
      ->select('MAX(root.notifiedAt)')
      ->getQuery()
      ->getSingleScalarResult();

    return \is_string($last) && '' !== $last ? new \DateTimeImmutable($last) : null;
  }

  /**
   * toReport
   *
   * @param \DateTimeImmutable $until
   *
   * @return QueryBuilder
   */
  private function toReport(\DateTimeImmutable $until): QueryBuilder
  {
    return $this->criteriaToReport($this->createQueryBuilder('root'), $until);
  }

  /**
   * criteriaToReport
   * New errors, never reported or that came back after their last report, up to $until.
   *
   * @param QueryBuilder       $builder
   * @param \DateTimeImmutable $until
   *
   * @return QueryBuilder
   */
  private function criteriaToReport(QueryBuilder $builder, \DateTimeImmutable $until): QueryBuilder
  {
    return $builder
      ->andWhere('root.status = :reportStatus')
      ->andWhere('root.notifiedAt IS NULL OR root.regressedAt > root.notifiedAt')
      ->andWhere('COALESCE(root.regressedAt, root.firstSeen) <= :reportUntil')
      ->setParameter('reportStatus', ErrorStatus::New->value)
      ->setParameter('reportUntil', $until, Types::DATETIME_IMMUTABLE);
  }
}
