<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Entity\Interfaces;

use Austral\Contracts\Entity\EntityInterface;
use Austral\LogBundle\Enum\ErrorStatus;
use Doctrine\Common\Collections\Collection;

/**
 * Austral Error Log Interface.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @extends EntityInterface<object>
 */
interface ErrorLogInterface extends EntityInterface
{
  public function getFingerprint(): string;

  /**
   * Highest Monolog level of the occurrences (Monolog\Level value).
   */
  public function getLevel(): int;

  public function getChannel(): string;

  public function getExceptionClass(): ?string;

  /**
   * Message of the first occurrence.
   */
  public function getMessage(): string;

  /**
   * Path from the root of the project.
   */
  public function getFile(): ?string;

  public function getLine(): ?int;

  public function getStatus(): ErrorStatus;

  /**
   * Number of occurrences since the first one, purged occurrences included.
   */
  public function getOccurrences(): int;

  public function getFirstSeen(): \DateTimeImmutable;

  public function getLastSeen(): \DateTimeImmutable;

  /**
   * Last time a resolved or archived error came back.
   */
  public function getRegressedAt(): ?\DateTimeImmutable;

  public function getStatusChangedAt(): ?\DateTimeImmutable;

  /**
   * The occurrences kept, the last one first.
   *
   * @return Collection<int, ErrorOccurrenceInterface>
   */
  public function getOccurrenceList(): Collection;
}
