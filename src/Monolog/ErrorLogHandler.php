<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Monolog;

use Austral\LogBundle\Record\ErrorRecorder;
use Austral\LogBundle\Record\ErrorRecordFactory;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Austral Error Log Handler.
 *
 * Monolog handler added by AustralLogExtension::prepend(): every record of the level of "austral_log.level" or
 * higher, from any channel (HTTP, console, Messenger, $logger->error()...), is saved by the ErrorRecorder.
 *
 * Not recorded:
 *   - the HTTP errors of the visitors (4xx), except the codes of "austral_log.capture_client_errors" ([404, 403]...);
 *   - a record logged while an error is being saved (no loop when the database fails).
 *
 * It lets the record go to the other handlers (bubble). Monolog resets it with its logger (workers): the recorder
 * then tries the database again.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorLogHandler extends AbstractProcessingHandler
{
  /**
   * @var bool
   */
  private bool $writing = false;

  /**
   * ErrorLogHandler constructor
   *
   * @param ErrorRecordFactory $factory
   * @param ErrorRecorder      $recorder
   * @param string             $level               Monolog level name (error, critical...)
   * @param list<int>          $captureClientErrors 4xx codes recorded
   */
  public function __construct(
    private readonly ErrorRecordFactory $factory,
    private readonly ErrorRecorder $recorder,
    string $level = 'error',
    private readonly array $captureClientErrors = [],
  ) {
    parent::__construct(Level::fromName($level), true);
  }

  /**
   * {@inheritDoc}
   */
  public function isHandling(LogRecord $record): bool
  {
    return parent::isHandling($record) && !$this->writing && !$this->isClientError($record);
  }

  /**
   * {@inheritDoc}
   */
  public function reset(): void
  {
    // Called by Monolog\Logger::reset() (kernel.reset of the workers): the recorder tries the database again.
    parent::reset();
    $this->recorder->reset();
  }

  /**
   * {@inheritDoc}
   */
  protected function write(LogRecord $record): void
  {
    $this->writing = true;
    try
    {
      $this->recorder->record($this->factory->create($record));
    }
    catch (\Throwable)
    {
      // A log handler never breaks the application: the record is still in the other handlers.
    }
    finally
    {
      $this->writing = false;
    }
  }

  /**
   * isClientError
   *
   * @param LogRecord $record
   *
   * @return bool
   */
  private function isClientError(LogRecord $record): bool
  {
    $exception = $record->context['exception'] ?? null;
    if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500)
    {
      return false;
    }

    return !\in_array($exception->getStatusCode(), $this->captureClientErrors, true);
  }
}
