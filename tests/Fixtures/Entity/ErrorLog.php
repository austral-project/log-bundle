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

namespace Austral\LogBundle\Tests\Fixtures\Entity;

use Austral\LogBundle\Entity\ErrorLog as BaseErrorLog;
use Austral\LogBundle\Repository\ErrorLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Error of the application, as in the Skeleton.
 */
#[ORM\Table(name: 'austral_log_error')]
#[ORM\Index(name: 'austral_log_error_status', columns: ['status'])]
#[ORM\Index(name: 'austral_log_error_last_seen', columns: ['last_seen'])]
#[ORM\Entity(repositoryClass: ErrorLogRepository::class)]
class ErrorLog extends BaseErrorLog
{
}
