<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Record;

/**
 * Austral Error Record.
 *
 * An occurrence of an error, ready to be saved: built by the ErrorRecordFactory, saved by the ErrorRecorder.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final readonly class ErrorRecord
{
  /**
   * @param string                                                                                                                         $fingerprint
   * @param int                                                                                                                            $level          Monolog\Level value
   * @param string                                                                                                                         $channel
   * @param string|null                                                                                                                    $exceptionClass
   * @param string                                                                                                                         $message
   * @param string|null                                                                                                                    $file           from the root of the project
   * @param int|null                                                                                                                       $line
   * @param \DateTimeImmutable                                                                                                             $occurredAt
   * @param string                                                                                                                         $environment
   * @param string|null                                                                                                                    $url
   * @param string|null                                                                                                                    $method
   * @param string|null                                                                                                                    $route
   * @param string|null                                                                                                                    $userId
   * @param list<array{class: string, message: string, file: string, line: int, frames: list<array{file: ?string, line: ?int, function: string}>}> $trace
   * @param array<string, mixed>                                                                                                           $context
   */
  public function __construct(
    public string $fingerprint,
    public int $level,
    public string $channel,
    public ?string $exceptionClass,
    public string $message,
    public ?string $file,
    public ?int $line,
    public \DateTimeImmutable $occurredAt,
    public string $environment,
    public ?string $url = null,
    public ?string $method = null,
    public ?string $route = null,
    public ?string $userId = null,
    public array $trace = [],
    public array $context = [],
  ) {
  }
}
