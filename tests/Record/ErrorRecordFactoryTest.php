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

namespace Austral\LogBundle\Tests\Record;

use Austral\LogBundle\Context\Sanitizer;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

final class ErrorRecordFactoryTest extends TestCase
{
  public function testAnExceptionIsTheError(): void
  {
    $kit = new LogKit();
    $previous = new \LogicException('Cause of the error');
    $exception = $this->exception('secret-value', $previous);

    $record = $kit->factory->create($this->logRecord('Uncaught PHP Exception {class}: "{message}"', [
      'exception' => $exception,
      'class' => \RuntimeException::class,
      'message' => 'Order 12 failed',
    ], Level::Critical, 'request'));

    self::assertSame(\RuntimeException::class, $record->exceptionClass);
    self::assertSame('Order 12 failed', $record->message);
    self::assertSame($exception->getLine(), $record->line, 'The line of the exception, not of the log.');
    self::assertSame(__FILE__, $record->file, 'A file out of the project keeps its path.');
    self::assertSame(Level::Critical->value, $record->level);
    self::assertSame('request', $record->channel);
    self::assertSame('2026-11-26 10:00:00', $record->occurredAt->format('Y-m-d H:i:s'));
    self::assertSame('prod', $record->environment);

    self::assertCount(2, $record->trace);
    self::assertSame(\RuntimeException::class, $record->trace[0]['class']);
    self::assertSame(\LogicException::class, $record->trace[1]['class']);
    self::assertSame('Cause of the error', $record->trace[1]['message']);
    self::assertNotSame([], $record->trace[0]['frames']);
    self::assertSame(['file', 'line', 'function'], array_keys($record->trace[0]['frames'][0]), 'No arguments of the calls.');
    self::assertStringNotContainsString('secret-value', json_encode($record->trace, \JSON_THROW_ON_ERROR));

    self::assertArrayNotHasKey('exception', $record->context['context']);
    self::assertSame('Uncaught PHP Exception RuntimeException: "Order 12 failed"', $record->context['log_message']);
    self::assertSame(['php' => \PHP_VERSION, 'symfony' => Kernel::VERSION], $record->context['versions']);
  }

  public function testTheSameErrorWithOtherValuesHasTheSameFingerprint(): void
  {
    $kit = new LogKit();
    $first = $kit->factory->create($this->logRecord('Failed', ['exception' => $this->sameLine('Order 12 failed')]));
    $second = $kit->factory->create($this->logRecord('Failed', ['exception' => $this->sameLine('Order 13 failed')]));
    $other = $kit->factory->create($this->logRecord('Failed', ['exception' => $this->sameLine('Payment 13 refused')]));

    self::assertSame($first->fingerprint, $second->fingerprint);
    self::assertNotSame($first->fingerprint, $other->fingerprint);
    self::assertSame(64, \strlen($first->fingerprint));
  }

  public function testARecordWithoutExceptionIsItsMessageInItsChannel(): void
  {
    $kit = new LogKit();
    $first = $kit->factory->create($this->logRecord('Import of {file} failed for {user_password}', ['file' => 'pages.csv', 'user_password' => 'p4ss']));
    $second = $kit->factory->create($this->logRecord('Import of {file} failed for {user_password}', ['file' => 'news.csv', 'user_password' => 'other']));
    $otherChannel = $kit->factory->create($this->logRecord('Import of {file} failed for {user_password}', ['file' => 'pages.csv'], channel: 'import'));

    self::assertNull($first->exceptionClass);
    self::assertNull($first->file);
    self::assertNull($first->line);
    self::assertSame([], $first->trace);
    self::assertSame('Import of pages.csv failed for '.Sanitizer::MASK, $first->message, 'The placeholders are replaced, sensitive ones masked.');
    self::assertSame(Sanitizer::MASK, $first->context['context']['user_password']);
    self::assertArrayNotHasKey('log_message', $first->context);
    self::assertSame($first->fingerprint, $second->fingerprint, 'The template is the fingerprint, not the values.');
    self::assertNotSame($first->fingerprint, $otherChannel->fingerprint);
  }

  public function testTheRequestAndTheExtraAreInTheContext(): void
  {
    $kit = new LogKit();
    $request = Request::create('https://austral.dev/page?page=2&token=abc', 'POST', ['password' => 'secret'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer abc']);
    $request->attributes->set('_route', 'website_page');
    $kit->requestStack->push($request);

    $record = $kit->factory->create($this->logRecord('Failed', [], extra: ['request_id' => 'r-1', 'session_id' => 'abc']));

    self::assertSame('https://austral.dev/page?page=2&token=%2A%2A%2A', $record->url);
    self::assertSame('POST', $record->method);
    self::assertSame('website_page', $record->route);
    self::assertSame(Sanitizer::MASK, $record->context['headers']['authorization']);
    self::assertSame(['request_id' => 'r-1', 'session_id' => Sanitizer::MASK], $record->context['extra']);
    self::assertStringNotContainsString('secret', json_encode($record->context, \JSON_THROW_ON_ERROR), 'The body is never collected.');
  }

  public function testInTheConsoleThereIsNoRequest(): void
  {
    $kit = new LogKit();
    $record = $kit->factory->create($this->logRecord('Error thrown while running command "{command}"', ['command' => 'app:import', 'exception' => new \RuntimeException('Failed')], channel: 'console'));

    self::assertNull($record->url);
    self::assertNull($record->method);
    self::assertNull($record->route);
    self::assertNull($record->userId);
    self::assertSame([], $record->context['headers']);
    self::assertSame('app:import', $record->context['context']['command']);
  }

  /**
   * @param array<string, mixed> $context
   * @param array<string, mixed> $extra
   */
  private function logRecord(string $message, array $context = [], Level $level = Level::Error, string $channel = 'app', array $extra = []): LogRecord
  {
    return new LogRecord(new \DateTimeImmutable('2026-11-26 10:00:00'), $channel, $level, $message, $context, $extra);
  }

  private function exception(string $argument, \Throwable $previous): \RuntimeException
  {
    return new \RuntimeException('Order 12 failed', 0, $previous);
  }

  private function sameLine(string $message): \RuntimeException
  {
    return new \RuntimeException($message);
  }
}
