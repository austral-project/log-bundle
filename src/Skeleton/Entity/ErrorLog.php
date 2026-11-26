<?php

namespace App\Entity\Austral\LogBundle;

use Austral\LogBundle\Entity\ErrorLog as BaseErrorLog;
use Austral\LogBundle\Repository\ErrorLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Austral Error Log Entity.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
#[ORM\Table(name: 'austral_log_error')]
#[ORM\Index(name: 'austral_log_error_status', columns: ['status'])]
#[ORM\Index(name: 'austral_log_error_last_seen', columns: ['last_seen'])]
#[ORM\Entity(repositoryClass: ErrorLogRepository::class)]
class ErrorLog extends BaseErrorLog
{
}
