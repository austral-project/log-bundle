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

/**
 * Austral Error Log Repository.
 *
 * @extends EntityRepository<ErrorLogInterface>
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class ErrorLogRepository extends EntityRepository
{
  /**
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
}
