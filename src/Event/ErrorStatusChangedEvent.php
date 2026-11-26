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

use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Enum\ErrorStatus;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Austral Error Status Changed Event (LogEvents::STATUS_CHANGED).
 *
 * Dispatched after the flush: the new status is saved.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorStatusChangedEvent extends Event
{
  /**
   * ErrorStatusChangedEvent constructor
   *
   * @param ErrorLogInterface $error
   * @param ErrorStatus       $previousStatus
   */
  public function __construct(
    public readonly ErrorLogInterface $error,
    public readonly ErrorStatus $previousStatus,
  ) {
  }

  /**
   * getStatus
   *
   * @return ErrorStatus
   */
  public function getStatus(): ErrorStatus
  {
    return $this->error->getStatus();
  }
}
