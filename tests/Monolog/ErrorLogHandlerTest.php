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

namespace Austral\LogBundle\Tests\Monolog;

use Austral\LogBundle\Monolog\ErrorLogHandler;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ErrorLogHandlerTest extends TestCase
{
  public function testTheErrorsAreRecordedAndGoOnToTheOtherHandlers(): void
  {
    $kit = new LogKit();
    $other = new TestHandler();
    $logger = new Logger('app', [new ErrorLogHandler($kit->factory, $kit->recorder), $other]);

    $logger->warning('Slow request');
    $logger->error('Import failed');
    $logger->critical('Uncaught PHP Exception', ['exception' => new \LogicException('Boom')]);
    $logger->error('Service unavailable', ['exception' => new HttpException(503, 'Down')]);

    self::assertCount(3, $kit->errors()->findAll(), 'From the level "error".');
    self::assertCount(4, $other->getRecords(), 'Bubble: the other handlers get every record.');
  }

  public function testTheLevelIsConfigurable(): void
  {
    $kit = new LogKit();
    $logger = new Logger('app', [new ErrorLogHandler($kit->factory, $kit->recorder, 'critical')]);

    $logger->error('Import failed');
    $logger->critical('Uncaught PHP Exception', ['exception' => new \LogicException('Boom')]);

    self::assertCount(1, $kit->errors()->findAll());
  }

  public function testTheErrorsOfTheVisitorsAreNotRecorded(): void
  {
    $kit = new LogKit();
    $logger = new Logger('request', [new ErrorLogHandler($kit->factory, $kit->recorder)]);

    $logger->error('Uncaught PHP Exception', ['exception' => new NotFoundHttpException('No route')]);
    $logger->error('Uncaught PHP Exception', ['exception' => new AccessDeniedHttpException('Denied')]);
    $logger->error('Uncaught PHP Exception', ['exception' => new MethodNotAllowedHttpException(['GET'])]);

    self::assertSame([], $kit->errors()->findAll());
  }

  public function testTheChosenCodesAreRecorded(): void
  {
    $kit = new LogKit();
    $logger = new Logger('request', [new ErrorLogHandler($kit->factory, $kit->recorder, 'error', [404, 403])]);

    $logger->error('Uncaught PHP Exception', ['exception' => new NotFoundHttpException('No route')]);
    $logger->error('Uncaught PHP Exception', ['exception' => new AccessDeniedHttpException('Denied')]);
    $logger->error('Uncaught PHP Exception', ['exception' => new MethodNotAllowedHttpException(['GET'])]);

    $classes = array_map(static fn ($error): ?string => $error->getExceptionClass(), $kit->errors()->findAll());
    sort($classes);
    self::assertSame([AccessDeniedHttpException::class, NotFoundHttpException::class], $classes, 'The 405 is not in the list.');
  }

  public function testABrokenDatabaseDoesNotBreakTheApplication(): void
  {
    $kit = new LogKit(withSchema: false);
    $other = new TestHandler();
    $logger = new Logger('app', [new ErrorLogHandler($kit->factory, $kit->recorder), $other]);

    $logger->error('Import failed');
    $logger->error('Import failed again');

    self::assertCount(2, $other->getRecords());
    self::assertNotNull($kit->recorder->getLastFailure());
  }

  public function testTheResetOfTheLoggerResetsTheRecorder(): void
  {
    $kit = new LogKit(withSchema: false);
    $logger = new Logger('app', [new ErrorLogHandler($kit->factory, $kit->recorder)]);
    $logger->error('Import failed');
    self::assertNotNull($kit->recorder->getLastFailure());

    $logger->reset();

    self::assertNull($kit->recorder->getLastFailure());
  }
}
