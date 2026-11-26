<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Enum;

/**
 * Austral Error Status.
 *
 * When an error comes back: Resolved and Archived go back to New (regression), the others are kept (an ignored
 * error stays ignored, its counter goes on).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
enum ErrorStatus: string
{
  case New = 'new';
  case InProgress = 'in_progress';
  case Resolved = 'resolved';
  case Ignored = 'ignored';
  case Archived = 'archived';

  /**
   * regressions
   * The statuses that go back to New when the error comes back.
   *
   * @return list<self>
   */
  public static function regressions(): array
  {
    return [self::Resolved, self::Archived];
  }

  /**
   * isRegressedByRecurrence
   *
   * @return bool
   */
  public function isRegressedByRecurrence(): bool
  {
    return \in_array($this, self::regressions(), true);
  }

  /**
   * afterRecurrence
   * Status of the error when it comes back.
   *
   * @return self
   */
  public function afterRecurrence(): self
  {
    return $this->isRegressedByRecurrence() ? self::New : $this;
  }
}
