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

use Austral\LogBundle\Context\ContextCollector;
use Austral\LogBundle\Context\Sanitizer;
use Austral\LogBundle\Fingerprint\FingerprintGenerator;
use Monolog\LogRecord;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Austral Error Record Factory.
 *
 * Builds an ErrorRecord from a Monolog record. With an exception in the context ("exception" key, as Symfony,
 * Messenger and the console log them), the error is the exception: its class, file, line and message. Without
 * exception, the error is the message of the record in its channel.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorRecordFactory
{
  private const int MAX_FRAMES = 50;

  private const int MAX_EXCEPTIONS = 5;

  /**
   * @param FingerprintGenerator $fingerprints
   * @param Sanitizer            $sanitizer
   * @param ContextCollector     $collector
   * @param string               $environment
   */
  public function __construct(
    private readonly FingerprintGenerator $fingerprints,
    private readonly Sanitizer $sanitizer,
    private readonly ContextCollector $collector,
    private readonly string $environment,
  ) {
  }

  /**
   * @param LogRecord $record
   *
   * @return ErrorRecord
   */
  public function create(LogRecord $record): ErrorRecord
  {
    $context = $record->context;
    $exception = $context['exception'] ?? null;
    unset($context['exception']);
    $logMessage = $this->interpolate($record->message, $context);

    if ($exception instanceof \Throwable)
    {
      $exceptionClass = $exception::class;
      $file = $this->fingerprints->relativePath($exception->getFile());
      $line = $exception->getLine();
      $message = $this->sanitizer->truncate($exception->getMessage());
      $fingerprint = $this->fingerprints->generate($exceptionClass, $file, $line, $exception->getMessage());
      $trace = $this->trace($exception);
    }
    else
    {
      $exceptionClass = null;
      $file = null;
      $line = null;
      $message = $this->sanitizer->truncate($logMessage);
      $fingerprint = $this->fingerprints->generate("channel:{$record->channel}", null, null, $record->message);
      $trace = [];
    }

    $request = $this->collector->request();
    $occurrenceContext = [
      'context' => $this->sanitizer->sanitize($context),
      'extra' => $this->sanitizer->sanitize($record->extra),
      'headers' => $request['headers'],
      'versions' => ['php' => \PHP_VERSION, 'symfony' => Kernel::VERSION],
    ];
    if ($logMessage !== $message)
    {
      $occurrenceContext['log_message'] = $this->sanitizer->truncate($logMessage);
    }

    return new ErrorRecord(
      fingerprint: $fingerprint,
      level: $record->level->value,
      channel: $this->sanitizer->truncate($record->channel),
      exceptionClass: $exceptionClass,
      message: $message,
      file: $file,
      line: $line,
      occurredAt: \DateTimeImmutable::createFromInterface($record->datetime),
      environment: $this->environment,
      url: $request['url'],
      method: $request['method'],
      route: $request['route'],
      userId: $this->collector->userId(),
      trace: $trace,
      context: $occurrenceContext,
    );
  }

  /**
   * The placeholders of PSR-3 ("User {id} not found") replaced by the values of the context, sensitive keys
   * masked.
   *
   * @param string                  $message
   * @param array<array-key, mixed> $context
   *
   * @return string
   */
  private function interpolate(string $message, array $context): string
  {
    if (!str_contains($message, '{'))
    {
      return $message;
    }

    return preg_replace_callback('/\{([A-Za-z0-9_.]+)\}/', function (array $matches) use ($context): string {
      $key = $matches[1];
      if (!\array_key_exists($key, $context))
      {
        return $matches[0];
      }
      if ($this->sanitizer->isSensitiveKey($key))
      {
        return Sanitizer::MASK;
      }

      return $this->string($context[$key]);
    }, $message) ?? $message;
  }

  /**
   * @param mixed $value
   *
   * @return string
   */
  private function string(mixed $value): string
  {
    try
    {
      return match (true)
      {
        null === $value => 'null',
        \is_bool($value) => $value ? 'true' : 'false',
        \is_scalar($value), $value instanceof \Stringable => (string) $value,
        $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
        \is_object($value) => '[object '.$value::class.']',
        default => '['.get_debug_type($value).']',
      };
    }
    catch (\Throwable)
    {
      return '['.get_debug_type($value).']';
    }
  }

  /**
   * The exception and its previous exceptions, with their frames, without the arguments of the calls.
   *
   * @param \Throwable $exception
   *
   * @return list<array{class: string, message: string, file: string, line: int, frames: list<array{file: ?string, line: ?int, function: string}>}>
   */
  private function trace(\Throwable $exception): array
  {
    $trace = [];
    $current = $exception;
    while (null !== $current && \count($trace) < self::MAX_EXCEPTIONS)
    {
      $frames = [];
      foreach (\array_slice($current->getTrace(), 0, self::MAX_FRAMES) as $frame)
      {
        $frames[] = [
          'file' => $this->fingerprints->relativePath($frame['file'] ?? null),
          'line' => $frame['line'] ?? null,
          'function' => (isset($frame['class']) ? $frame['class'].($frame['type'] ?? '::') : '').$frame['function'],
        ];
      }
      $trace[] = [
        'class' => $current::class,
        'message' => $this->sanitizer->truncate($current->getMessage()),
        'file' => $this->fingerprints->relativePath($current->getFile()) ?? '',
        'line' => $current->getLine(),
        'frames' => $frames,
      ];
      $current = $current->getPrevious();
    }

    return $trace;
  }
}
