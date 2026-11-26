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
  public function getError(): ErrorLogInterface;

  public function getOccurredAt(): \DateTimeImmutable;

  /**
   * Monolog level of this occurrence (Monolog\Level value).
   */
  public function getLevel(): int;

  public function getMessage(): string;

  /**
   * URL of the request, sensitive query parameters masked; null in the console and the workers.
   */
  public function getUrl(): ?string;

  public function getMethod(): ?string;

  public function getRoute(): ?string;

  public function getUserId(): ?string;

  public function getEnvironment(): string;

  /**
   * The exception and its previous exceptions, each with its frames, without the arguments of the calls;
   * empty when the record has no exception.
   *
   * @return list<array{class: string, message: string, file: string, line: int, frames: list<array{file: ?string, line: ?int, function: string}>}>
   */
  public function getTrace(): array;

  /**
   * Context of the record, headers and versions, sensitive values masked.
   *
   * @return array<string, mixed>
   */
  public function getContext(): array;
}
