<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Event;

use Austral\LogBundle\Enum\ErrorStatus;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Austral Errors Purged Event (LogEvents::PURGED).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorsPurgedEvent extends Event
{
  /**
   * ErrorsPurgedEvent constructor
   *
   * @param int                $errors      number of errors deleted
   * @param int                $occurrences number of occurrences deleted with them
   * @param \DateTimeImmutable $before      status changed before this date
   * @param list<ErrorStatus>  $statuses
   */
  public function __construct(
    public readonly int $errors,
    public readonly int $occurrences,
    public readonly \DateTimeImmutable $before,
    public readonly array $statuses,
  ) {
  }
}
