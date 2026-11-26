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

/**
 * Austral Error Occurrence Interface.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @extends EntityInterface<object>
 */
interface ErrorOccurrenceInterface extends EntityInterface
{
  /**
   * getError
   *
   * @return ErrorLogInterface
   */
  public function getError(): ErrorLogInterface;

  /**
   * getOccurredAt
   *
   * @return \DateTimeImmutable
   */
  public function getOccurredAt(): \DateTimeImmutable;

  /**
   * getLevel
   * Monolog level of this occurrence (Monolog\Level value).
   *
   * @return int
   */
  public function getLevel(): int;

  /**
   * getMessage
   *
   * @return string
   */
  public function getMessage(): string;

  /**
   * getUrl
   * URL of the request, sensitive query parameters masked; null in the console and the workers.
   *
   * @return string|null
   */
  public function getUrl(): ?string;

  /**
   * getMethod
   *
   * @return string|null
   */
  public function getMethod(): ?string;

  /**
   * getRoute
   *
   * @return string|null
   */
  public function getRoute(): ?string;

  /**
   * getUserId
   *
   * @return string|null
   */
  public function getUserId(): ?string;

  /**
   * getEnvironment
   *
   * @return string
   */
  public function getEnvironment(): string;

  /**
   * getTrace
   * The exception and its previous exceptions, each with its frames, without the arguments of the calls;
   * empty when the record has no exception.
   *
   * @return list<array{class: string, message: string, file: string, line: int, frames: list<array{file: ?string, line: ?int, function: string}>}>
   */
  public function getTrace(): array;

  /**
   * getContext
   * Context of the record, headers and versions, sensitive values masked.
   *
   * @return array<string, mixed>
   */
  public function getContext(): array;
}
