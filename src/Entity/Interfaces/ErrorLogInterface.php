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
  /**
   * getFingerprint
   *
   * @return string
   */
  public function getFingerprint(): string;

  /**
   * getLevel
   * Highest Monolog level of the occurrences (Monolog\Level value).
   *
   * @return int
   */
  public function getLevel(): int;

  /**
   * getChannel
   *
   * @return string
   */
  public function getChannel(): string;

  /**
   * getExceptionClass
   *
   * @return string|null
   */
  public function getExceptionClass(): ?string;

  /**
   * getMessage
   * Message of the first occurrence.
   *
   * @return string
   */
  public function getMessage(): string;

  /**
   * getFile
   * Path from the root of the project.
   *
   * @return string|null
   */
  public function getFile(): ?string;

  /**
   * getLine
   *
   * @return int|null
   */
  public function getLine(): ?int;

  /**
   * getStatus
   *
   * @return ErrorStatus
   */
  public function getStatus(): ErrorStatus;

  /**
   * changeStatus
   * Changes the status and its date; false when the error already has this status.
   *
   * @param ErrorStatus        $status
   * @param \DateTimeImmutable $changedAt
   *
   * @return bool
   */
  public function changeStatus(ErrorStatus $status, \DateTimeImmutable $changedAt): bool;

  /**
   * getOccurrences
   *  Number of occurrences since the first one, purged occurrences included.
   *
   * @return int
   */
  public function getOccurrences(): int;

  /**
   * getFirstSeen
   *
   * @return \DateTimeImmutable
   */
  public function getFirstSeen(): \DateTimeImmutable;

  /**
   * getLastSeen
   *
   * @return \DateTimeImmutable
   */
  public function getLastSeen(): \DateTimeImmutable;

  /**
   * getRegressedAt
   * Last time a resolved or archived error came back.
   *
   * @return \DateTimeImmutable|null
   */
  public function getRegressedAt(): ?\DateTimeImmutable;

  /**
   * getStatusChangedAt
   * Last change of the status: by the StatusManager, or by a regression.
   *
   * @return \DateTimeImmutable|null
   */
  public function getStatusChangedAt(): ?\DateTimeImmutable;

  /**
   * getNotifiedAt
   * Last email report that listed the error; a regression after this date is reported again.
   *
   * @return \DateTimeImmutable|null
   */
  public function getNotifiedAt(): ?\DateTimeImmutable;

  /**
   * getOccurrenceList
   * The occurrences kept, the last one first.
   *
   * @return Collection<int, ErrorOccurrenceInterface>
   */
  public function getOccurrenceList(): Collection;
}
