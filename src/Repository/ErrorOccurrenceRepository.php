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
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;

/**
 * Austral Error Occurrence Repository.
 *
 * @extends EntityRepository<ErrorOccurrenceInterface>
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class ErrorOccurrenceRepository extends EntityRepository
{
}
