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

namespace Austral\LogBundle\Tests\Status;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Event\ErrorStatusChangedEvent;
use Austral\LogBundle\Event\LogEvents;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use PHPUnit\Framework\TestCase;

final class StatusManagerTest extends TestCase
{
  public function testTheStatusIsChangedAndSaved(): void
  {
    $kit = new LogKit();
    $events = $this->listen($kit);
    $error = $kit->error();

    self::assertTrue($kit->statuses->change($error, ErrorStatus::Resolved));
    $kit->clear();

    $saved = $kit->errors()->retrieveByFingerprint($error->getFingerprint());
    self::assertNotNull($saved);
    self::assertSame(ErrorStatus::Resolved, $saved->getStatus());
    self::assertSame('2026-11-26 10:00:00', $saved->getStatusChangedAt()?->format('Y-m-d H:i:s'));
    self::assertCount(1, $events);
    self::assertSame(ErrorStatus::New, $events[0]->previousStatus);
    self::assertSame(ErrorStatus::Resolved, $events[0]->getStatus());
    self::assertSame($error->getId(), $events[0]->error->getId());
  }

  public function testTheSameStatusChangesNothing(): void
  {
    $kit = new LogKit();
    $events = $this->listen($kit);
    $error = $kit->error();

    self::assertFalse($kit->statuses->change($error, ErrorStatus::New));
    self::assertNull($error->getStatusChangedAt(), 'No new date.');
    self::assertCount(0, $events, 'No event.');
  }

  public function testSeveralErrorsAtOnce(): void
  {
    $kit = new LogKit();
    $first = $kit->error(['fingerprint' => str_repeat('a', 64)]);
    $second = $kit->error(['fingerprint' => str_repeat('b', 64)]);
    $archived = $kit->error(['fingerprint' => str_repeat('c', 64)]);
    $kit->statuses->change($archived, ErrorStatus::Archived);
    $events = $this->listen($kit, reset: true);

    self::assertSame(2, $kit->statuses->changeAll([$first, $second, $archived], ErrorStatus::Archived));
    self::assertCount(2, $events, 'Only for the errors that changed.');
    $kit->clear();
    foreach ($kit->errors()->findAll() as $error)
    {
      self::assertSame(ErrorStatus::Archived, $error->getStatus());
    }
  }

  public function testAResolvedErrorThatComesBackIsNewAgainWithItsDates(): void
  {
    $kit = new LogKit();
    $error = $kit->error();
    $kit->statuses->change($error, ErrorStatus::Resolved);
    $kit->clear();

    $kit->recorder->record($kit->record(['occurredAt' => new \DateTimeImmutable('2026-11-27 08:00:00')]));

    $saved = $kit->errors()->retrieveByFingerprint($error->getFingerprint());
    self::assertNotNull($saved);
    self::assertSame(ErrorStatus::New, $saved->getStatus());
    self::assertSame('2026-11-27 08:00:00', $saved->getRegressedAt()?->format('Y-m-d H:i:s'));
    self::assertSame('2026-11-27 08:00:00', $saved->getStatusChangedAt()?->format('Y-m-d H:i:s'), 'The regression is a change of status.');
  }

  /**
   * @return \ArrayObject<int, ErrorStatusChangedEvent>
   */
  private function listen(LogKit $kit, bool $reset = false): \ArrayObject
  {
    if ($reset)
    {
      foreach ($kit->dispatcher->getListeners(LogEvents::STATUS_CHANGED) as $listener)
      {
        $kit->dispatcher->removeListener(LogEvents::STATUS_CHANGED, $listener);
      }
    }
    /** @var \ArrayObject<int, ErrorStatusChangedEvent> $events */
    $events = new \ArrayObject();
    $kit->dispatcher->addListener(LogEvents::STATUS_CHANGED, static function (ErrorStatusChangedEvent $event) use ($events): void {
      $events->append($event);
    });

    return $events;
  }
}
