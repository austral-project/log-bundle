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

namespace Austral\LogBundle\Tests\Bridge\Admin;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Record\ErrorRecord;
use Austral\LogBundle\Record\ErrorRecorder;
use Austral\LogBundle\Tests\Fixtures\AdminKernel;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorLog;
use Austral\LogBundle\Tests\Fixtures\Security\Group;
use Austral\LogBundle\Tests\Fixtures\Security\Role;
use Austral\LogBundle\Tests\Fixtures\Security\User;
use Austral\SecurityBundle\Entity\Interfaces\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The test application on an empty database, with root and an editor whose group gives the roles of
 * editorRoles(). The errors are saved by the recorder of the bundle, as on a site.
 */
abstract class AdminWebTestCase extends WebTestCase
{
  protected KernelBrowser $client;

  /**
   * @param array<string, mixed> $options
   */
  protected static function createKernel(array $options = []): KernelInterface
  {
    return new AdminKernel('test', false);
  }

  /**
   * @return list<string>
   */
  protected static function editorRoles(): array
  {
    return ['ROLE_LOG', 'ROLE_LOG_ERRORS', 'ROLE_LOG_ERRORS_SHOW'];
  }

  protected function setUp(): void
  {
    $this->client = static::createClient();
    $this->client->disableReboot();
    $doctrine = $this->doctrine();
    $schemaTool = new SchemaTool($doctrine);
    $metadata = $doctrine->getMetadataFactory()->getAllMetadata();
    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $this->user('root', UserInterface::USER_TYPE_ROOT);
    $editor = $this->user('editor', UserInterface::USER_TYPE_ADMIN);
    $group = new Group();
    $group->setName('Editors')->setKeyname('editors');
    foreach (static::editorRoles() as $name)
    {
      $role = new Role();
      $role->setRole($name)->setName($name);
      $doctrine->persist($role);
      $group->addRole($role);
    }
    $doctrine->persist($group);
    $editor->addGroup($group);
    $doctrine->flush();
  }

  protected function doctrine(): EntityManagerInterface
  {
    /* @var EntityManagerInterface */
    return static::getContainer()->get('doctrine.orm.entity_manager');
  }

  /**
   * An error saved by the recorder of the bundle, then read with the ORM.
   *
   * @param array<string, mixed> $values
   */
  protected function error(array $values = [], ?ErrorStatus $status = null): ErrorLog
  {
    $recorder = static::getContainer()->get('austral.log.recorder');
    self::assertInstanceOf(ErrorRecorder::class, $recorder);
    $record = new ErrorRecord(...$values + [
      'fingerprint' => str_repeat('a', 64),
      'level' => 500,
      'channel' => 'request',
      'exceptionClass' => \RuntimeException::class,
      'message' => 'Order 12 failed',
      'file' => 'src/Service/OrderService.php',
      'line' => 42,
      'occurredAt' => new \DateTimeImmutable('-1 hour'),
      'environment' => 'prod',
      'url' => 'https://austral.dev/orders/12',
      'method' => 'POST',
      'route' => 'order_pay',
      'trace' => [['class' => \RuntimeException::class, 'message' => 'Order 12 failed', 'file' => 'src/Service/OrderService.php', 'line' => 42, 'frames' => [['file' => 'src/Controller/OrderController.php', 'line' => 18, 'function' => 'App\Service\OrderService->pay']]]],
      'context' => ['context' => ['order' => 12]],
    ]);
    self::assertTrue($recorder->record($record), (string) $recorder->getLastFailure());
    if (null !== $status)
    {
      $this->doctrine()->getConnection()->update('austral_log_error', ['status' => $status->value], ['fingerprint' => $record->fingerprint]);
    }
    $this->doctrine()->clear();

    return $this->findError($record->fingerprint);
  }

  protected function findError(string $fingerprint = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): ErrorLog
  {
    $error = $this->doctrine()->getRepository(ErrorLog::class)->findOneBy(['fingerprint' => $fingerprint]);
    self::assertInstanceOf(ErrorLog::class, $error);

    return $error;
  }

  protected function user(string $username, string $type): User
  {
    $user = new User();
    $user->setUsername($username)->setEmail("{$username}@austral.dev")->setIsActive(true)->setTypeUser($type)->setLanguage('en');
    $user->setPassword('secret');
    $this->doctrine()->persist($user);
    $this->doctrine()->flush();

    return $user;
  }

  protected function login(string $username): void
  {
    $user = $this->doctrine()->getRepository(User::class)->findOneBy(['username' => $username]);
    self::assertInstanceOf(User::class, $user);
    $this->client->loginUser($user, 'austral_admin');
  }

  /**
   * CSRF token of the admin, read from a page of the admin.
   */
  protected function csrfToken(string $url = '/austral-admin/'): string
  {
    $crawler = $this->client->request('GET', $url);
    $meta = $crawler->filter('meta[name="austral-admin-csrf-token"]');
    self::assertGreaterThan(0, $meta->count(), "No CSRF token on {$url}.");

    return (string) $meta->first()->attr('content');
  }

  /**
   * FrameworkBundle::boot() registers the ErrorHandler of Symfony as exception handler (without symfony/runtime):
   * it is removed after the test, PHPUnit fails a test that leaves a handler (risky).
   */
  protected function tearDown(): void
  {
    parent::tearDown();
    self::restoreExceptionHandlers();
  }

  private static function restoreExceptionHandlers(): void
  {
    while (true)
    {
      $handler = set_exception_handler(static fn (\Throwable $exception) => null);
      restore_exception_handler();
      if (!\is_array($handler) || !$handler[0] instanceof ErrorHandler)
      {
        return;
      }
      restore_exception_handler();
    }
  }
}
