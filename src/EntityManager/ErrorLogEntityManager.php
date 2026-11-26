<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\EntityManager;

use Austral\EntityBundle\EntityManager\EntityManager;
use Austral\LogBundle\Repository\ErrorLogRepository;

/**
 * Austral Error Log Entity Manager.
 *
 * The errors are created by the ErrorRecorder, never by this manager: it reads them and saves their status.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @extends EntityManager<object>
 */
class ErrorLogEntityManager extends EntityManager
{
  /**
   * {@inheritDoc}
   */
  public function getRepository(?string $entity = null): ?ErrorLogRepository
  {
    return $this->repository instanceof ErrorLogRepository ? $this->repository : null;
  }
}
