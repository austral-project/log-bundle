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

namespace Austral\LogBundle\Tests\Fixtures;

use Psr\Clock\ClockInterface;

/**
 * A clock that does not move: the dates of the tests are known.
 */
final class FrozenClock implements ClockInterface
{
  private \DateTimeImmutable $now;

  public function __construct(string $now)
  {
    $this->now = new \DateTimeImmutable($now);
  }

  public function now(): \DateTimeImmutable
  {
    return $this->now;
  }
}
