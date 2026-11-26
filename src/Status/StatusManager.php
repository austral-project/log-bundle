<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Status;

use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\EntityManager\ErrorLogEntityManager;
use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Event\ErrorStatusChangedEvent;
use Austral\LogBundle\Event\LogEvents;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Austral Status Manager.
 *
 * Changes the status of one or several errors (the actions of the back office), saves them in one flush, then
 * dispatches LogEvents::STATUS_CHANGED for each error that changed. An error that already has the status is left
 * as it is: no new date, no event.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class StatusManager
{
  /**
   * StatusManager constructor
   *
   * @param ErrorLogEntityManager    $errors
   * @param EventDispatcherInterface $dispatcher
   * @param ClockInterface|null      $clock
   */
  public function __construct(
    private readonly ErrorLogEntityManager $errors,
    private readonly EventDispatcherInterface $dispatcher,
    private readonly ?ClockInterface $clock = null,
  ) {
  }

  /**
   * change
   *
   * @param ErrorLogInterface $error
   * @param ErrorStatus       $status
   *
   * @return bool false when the error already had this status
   */
  public function change(ErrorLogInterface $error, ErrorStatus $status): bool
  {
    return 1 === $this->changeAll([$error], $status);
  }

  /**
   * changeAll
   *
   * @param iterable<ErrorLogInterface> $errors
   * @param ErrorStatus                 $status
   *
   * @return int number of errors whose status changed
   */
  public function changeAll(iterable $errors, ErrorStatus $status): int
  {
    $now = $this->clock?->now() ?? new \DateTimeImmutable();
    $events = [];
    foreach ($errors as $error)
    {
      $previous = $error->getStatus();
      if ($error->changeStatus($status, $now))
      {
        $this->errors->update($error, false);
        $events[] = new ErrorStatusChangedEvent($error, $previous);
      }
    }
    if ([] === $events)
    {
      return 0;
    }
    $this->errors->flush();
    foreach ($events as $event)
    {
      $this->dispatcher->dispatch($event, LogEvents::STATUS_CHANGED);
    }

    return \count($events);
  }
}
