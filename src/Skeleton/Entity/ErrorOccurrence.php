<?php

namespace App\Entity\Austral\LogBundle;

use Austral\LogBundle\Entity\ErrorOccurrence as BaseErrorOccurrence;
use Austral\LogBundle\Repository\ErrorOccurrenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Austral Error Occurrence Entity.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
#[ORM\Table(name: 'austral_log_error_occurrence')]
#[ORM\Entity(repositoryClass: ErrorOccurrenceRepository::class)]
class ErrorOccurrence extends BaseErrorOccurrence
{
}
