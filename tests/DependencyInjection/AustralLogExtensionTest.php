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

namespace Austral\LogBundle\Tests\DependencyInjection;

use Austral\LogBundle\DependencyInjection\AustralLogExtension;
use Austral\LogBundle\DependencyInjection\Compiler\LogConnectionPass;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Austral\LogBundle\Monolog\ErrorLogHandler;
use Austral\LogBundle\Record\ErrorRecorder;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorLog;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorOccurrence;
use Austral\LogBundle\Tests\Fixtures\ExtensionStub;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use Austral\LogBundle\Tests\Fixtures\SingleManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class AustralLogExtensionTest extends TestCase
{
  private const array CONFIG = [
    'entities' => ['error' => ErrorLog::class, 'occurrence' => ErrorOccurrence::class],
  ];

  public function testTheServicesAreExplicit(): void
  {
    $container = $this->load();

    foreach ($container->getDefinitions() as $id => $definition)
    {
      if (str_starts_with($id, 'Austral\\'))
      {
        self::assertFalse($definition->isAutowired(), "{$id} is autowired.");
        self::assertFalse($definition->isAutoconfigured(), "{$id} is autoconfigured.");
        self::assertFalse($definition->isPublic(), "{$id} is public: only the austral.* aliases are.");
      }
    }
    self::assertTrue($container->getAlias('austral.log.recorder')->isPublic());
    self::assertFalse($container->getAlias('austral.log.monolog_handler')->isPublic(), 'A handler is never read from the container.');
  }

  public function testTheParameters(): void
  {
    $container = $this->load(['level' => 'critical', 'capture_client_errors' => [404], 'connection' => 'logs', 'occurrences_limit' => 10]);

    self::assertSame(ErrorLog::class, $container->getParameter('austral.entity.error_log.class'));
    self::assertSame(ErrorOccurrence::class, $container->getParameter('austral.entity.error_occurrence.class'));
    self::assertSame('critical', $container->getParameter('austral_log.level'));
    self::assertSame([404], $container->getParameter('austral_log.capture_client_errors'));
    self::assertSame('logs', $container->getParameter('austral_log.connection'));
    self::assertSame(10, $container->getParameter('austral_log.occurrences_limit'));
  }

  public function testTheHandlerAndTheEntitiesAreAddedWithoutRecipe(): void
  {
    $container = $this->prepend(self::CONFIG);

    self::assertSame([[
      'handlers' => [
        AustralLogExtension::MONOLOG_HANDLER => ['type' => 'service', 'id' => 'austral.log.monolog_handler'],
      ],
    ]], $container->getExtensionConfig('monolog'));
    self::assertSame([[
      'orm' => [
        'resolve_target_entities' => [
          ErrorLogInterface::class => ErrorLog::class,
          ErrorOccurrenceInterface::class => ErrorOccurrence::class,
        ],
      ],
    ]], $container->getExtensionConfig('doctrine'));
  }

  public function testDisabledNoHandler(): void
  {
    $container = $this->prepend(['enabled' => false] + self::CONFIG);

    self::assertSame([], $container->getExtensionConfig('monolog'));
    self::assertNotSame([], $container->getExtensionConfig('doctrine'), 'The entities stay mapped: the errors already saved can be read.');
  }

  public function testWithoutMonologNorDoctrine(): void
  {
    $container = new ContainerBuilder();
    $container->prependExtensionConfig('austral_log', self::CONFIG);
    new AustralLogExtension()->prepend($container);

    self::assertFalse($container->hasExtension('monolog'));
    self::assertFalse($container->hasExtension('doctrine'));
  }

  public function testTheServicesAreBuiltWithoutConnectingToTheDatabase(): void
  {
    $container = $this->load();
    $container->setDefinition('doctrine.dbal.connection', new Definition(Connection::class))
      ->setFactory([DriverManager::class, 'getConnection'])
      ->setAbstract(true);
    $container->setDefinition('doctrine.dbal.default_connection', new ChildDefinition('doctrine.dbal.connection'))
      ->setArguments([['driver' => 'pdo_sqlite', 'path' => '/nonexistent/folder/austral.sqlite']])
      ->setPublic(true);
    $container->register('doctrine', SingleManagerRegistry::class)->setSynthetic(true);
    $container->setAlias('test.monolog_handler', ErrorLogHandler::class)->setPublic(true);
    $container->addCompilerPass(new LogConnectionPass());
    $container->compile();
    $container->set('doctrine', new SingleManagerRegistry(new LogKit()->doctrine));

    self::assertInstanceOf(ErrorRecorder::class, $container->get('austral.log.recorder'));
    self::assertInstanceOf(ErrorLogHandler::class, $container->get('test.monolog_handler'));
    self::assertNull($container->get('austral.log.recorder')->getLastFailure(), 'Nothing is opened before an error.');
  }

  /**
   * @param array<string, mixed> $config
   */
  private function load(array $config = []): ContainerBuilder
  {
    $container = new ContainerBuilder();
    $container->setParameter('kernel.project_dir', LogKit::PROJECT_DIR);
    $container->setParameter('kernel.environment', 'test');
    new AustralLogExtension()->load([$config + self::CONFIG], $container);

    return $container;
  }

  /**
   * @param array<string, mixed> $config
   */
  private function prepend(array $config): ContainerBuilder
  {
    $container = new ContainerBuilder();
    $container->registerExtension(new ExtensionStub('monolog'));
    $container->registerExtension(new ExtensionStub('doctrine'));
    $container->prependExtensionConfig('austral_log', $config);
    new AustralLogExtension()->prepend($container);

    return $container;
  }
}
