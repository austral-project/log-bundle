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

use Austral\LogBundle\Context\ContextCollector;
use Austral\LogBundle\Context\Sanitizer;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Austral\LogBundle\EntityManager\ErrorLogEntityManager;
use Austral\LogBundle\Fingerprint\FingerprintGenerator;
use Austral\LogBundle\Purge\ErrorPurger;
use Austral\LogBundle\Record\ErrorRecord;
use Austral\LogBundle\Record\ErrorRecorder;
use Austral\LogBundle\Record\ErrorRecordFactory;
use Austral\LogBundle\Report\ErrorReporter;
use Austral\LogBundle\Repository\ErrorLogRepository;
use Austral\LogBundle\Status\StatusManager;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorLog;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorOccurrence;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The services of the bundle on a SQLite database in a temporary file, as the container wires them.
 *
 * A file, not an in-memory database: the recorder writes with its own connection, which must see the same tables
 * as the entity manager of the application.
 */
final class LogKit
{
  public const string PROJECT_DIR = '/srv/austral';

  public readonly string $path;
  public readonly EntityManagerInterface $doctrine;
  public readonly Connection $logConnection;
  public readonly RequestStack $requestStack;
  public readonly Sanitizer $sanitizer;
  public readonly FingerprintGenerator $fingerprints;
  public readonly ErrorRecordFactory $factory;
  public readonly ErrorRecorder $recorder;
  public readonly FrozenClock $clock;
  public readonly EventDispatcher $dispatcher;
  public readonly ErrorLogEntityManager $errorManager;
  public readonly StatusManager $statuses;
  public readonly ErrorPurger $purger;
  public readonly SingleManagerRegistry $registry;

  public function __construct(int $occurrencesLimit = 50, bool $withSchema = true)
  {
    $path = tempnam(sys_get_temp_dir(), 'austral_log_');
    if (false === $path)
    {
      throw new \RuntimeException('No temporary file.');
    }
    $this->path = $path;

    $config = new Configuration();
    $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Entity']));
    $config->enableNativeLazyObjects(true);

    $resolver = new ResolveTargetEntityListener();
    $resolver->addResolveTargetEntity(ErrorLogInterface::class, ErrorLog::class, []);
    $resolver->addResolveTargetEntity(ErrorOccurrenceInterface::class, ErrorOccurrence::class, []);
    $eventManager = new EventManager();
    $eventManager->addEventListener([Events::loadClassMetadata, Events::onClassMetadataNotFound], $resolver);

    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path], $config);
    $this->doctrine = new EntityManager($connection, $config, $eventManager);
    if ($withSchema)
    {
      new SchemaTool($this->doctrine)->createSchema($this->doctrine->getMetadataFactory()->getAllMetadata());
    }

    // The second connection of the recorder (LogConnectionPass in the bundle).
    $this->logConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path]);

    $this->requestStack = new RequestStack();
    $this->sanitizer = new Sanitizer();
    $this->fingerprints = new FingerprintGenerator(self::PROJECT_DIR);
    $this->factory = new ErrorRecordFactory($this->fingerprints, $this->sanitizer, new ContextCollector($this->sanitizer, $this->requestStack), 'prod');
    $this->registry = $registry = new SingleManagerRegistry($this->doctrine);
    $this->recorder = new ErrorRecorder(fn (): Connection => $this->logConnection, fn (): SingleManagerRegistry => $registry, ErrorLog::class, ErrorOccurrence::class, $occurrencesLimit);

    $this->clock = new FrozenClock('2026-11-26 10:00:00');
    $this->dispatcher = new EventDispatcher();
    $this->errorManager = new ErrorLogEntityManager($this->doctrine, $this->dispatcher, ErrorLog::class);
    $this->statuses = new StatusManager($this->errorManager, $this->dispatcher, $this->clock);
    $this->purger = new ErrorPurger($registry, $this->dispatcher, ErrorLog::class, ErrorOccurrence::class, $this->clock);
  }

  public function __destruct()
  {
    $this->doctrine->getConnection()->close();
    $this->logConnection->close();
    if (is_file($this->path))
    {
      unlink($this->path);
    }
  }

  /**
   * A record, the values the test needs changed.
   *
   * @param array<string, mixed> $values
   */
  public function record(array $values = []): ErrorRecord
  {
    return new ErrorRecord(...$values + [
      'fingerprint' => str_repeat('a', 64),
      'level' => 400,
      'channel' => 'app',
      'exceptionClass' => \RuntimeException::class,
      'message' => 'Something went wrong',
      'file' => 'src/Service.php',
      'line' => 12,
      'occurredAt' => new \DateTimeImmutable('2026-11-26 10:00:00'),
      'environment' => 'prod',
    ]);
  }

  /**
   * The reporter of the bundle, with the templates of the bundle and a fake mailer.
   */
  public function reporter(FakeMailer $mailer, string $frequency = 'daily', int $limit = 50): ErrorReporter
  {
    return new ErrorReporter($this->registry, $mailer, self::twig(), ErrorLog::class, $frequency, ['dev@austral.dev', 'ops@austral.dev'], 'no-reply@austral.dev', 'Errors of the site', $limit, 'prod', $this->clock);
  }

  /**
   * Twig with the templates of the bundle (@AustralLog), as TwigBundle registers them.
   */
  public static function twig(): Environment
  {
    $loader = new FilesystemLoader();
    $loader->addPath(__DIR__.'/../../src/Resources/views', 'AustralLog');

    return new Environment($loader, ['strict_variables' => true]);
  }

  /**
   * An error saved by the recorder, then read with the ORM.
   *
   * @param array<string, mixed> $values
   */
  public function error(array $values = []): ErrorLog
  {
    $record = $this->record($values);
    if (!$this->recorder->record($record))
    {
      throw new \LogicException('Not recorded.', 0, $this->recorder->getLastFailure());
    }
    $error = $this->errors()->retrieveByFingerprint($record->fingerprint);
    if (!$error instanceof ErrorLog)
    {
      throw new \LogicException('Not found.');
    }

    return $error;
  }

  public function errors(): ErrorLogRepository
  {
    $repository = $this->doctrine->getRepository(ErrorLog::class);
    if (!$repository instanceof ErrorLogRepository)
    {
      throw new \LogicException('Wrong repository.');
    }

    return $repository;
  }

  /**
   * @return list<ErrorOccurrence>
   */
  public function occurrences(): array
  {
    /** @var list<ErrorOccurrence> $occurrences */
    $occurrences = $this->doctrine->getRepository(ErrorOccurrence::class)->findBy([], ['id' => 'DESC']);

    return $occurrences;
  }

  /**
   * The next reads come from the database, not from the identity map.
   */
  public function clear(): void
  {
    $this->doctrine->clear();
  }
}
