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

namespace Austral\LogBundle\Tests\Enum;

use Austral\LogBundle\Enum\ErrorStatus;
use PHPUnit\Framework\TestCase;

final class ErrorStatusTest extends TestCase
{
  public function testTheValuesAreStable(): void
  {
    self::assertSame(['new', 'in_progress', 'resolved', 'ignored', 'archived'], array_map(
      static fn (ErrorStatus $status): string => $status->value,
      ErrorStatus::cases(),
    ), 'They are saved in the database.');
  }

  public function testResolvedAndArchivedGoBackToNewWhenTheErrorComesBack(): void
  {
    self::assertSame([ErrorStatus::Resolved, ErrorStatus::Archived], ErrorStatus::regressions());
    self::assertSame(ErrorStatus::New, ErrorStatus::Resolved->afterRecurrence());
    self::assertSame(ErrorStatus::New, ErrorStatus::Archived->afterRecurrence());
    self::assertSame(ErrorStatus::Ignored, ErrorStatus::Ignored->afterRecurrence());
    self::assertSame(ErrorStatus::InProgress, ErrorStatus::InProgress->afterRecurrence());
    self::assertSame(ErrorStatus::New, ErrorStatus::New->afterRecurrence());
    self::assertTrue(ErrorStatus::Resolved->isRegressedByRecurrence());
    self::assertFalse(ErrorStatus::Ignored->isRegressedByRecurrence());
  }
}
