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

use Austral\AdminBundle\AustralAdminBundle;
use Austral\LogBundle\Bridge\Admin\ErrorLogAdmin;
use Austral\LogBundle\Bridge\Admin\Listener\ConfigurationCheckListener;
use Austral\LogBundle\Command\PurgeCommand;
use Austral\LogBundle\Command\ReportCommand;
use Austral\LogBundle\DependencyInjection\AustralLogExtension;
use Austral\LogBundle\DependencyInjection\Compiler\LogConnectionPass;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Austral\LogBundle\EntityManager\ErrorLogEntityManager;
use Austral\LogBundle\Monolog\ErrorLogHandler;
use Austral\LogBundle\Purge\ErrorPurger;
use Austral\LogBundle\Record\ErrorRecorder;
use Austral\LogBundle\Report\ErrorReporter;
use Austral\LogBundle\Status\StatusManager;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorLog;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorOccurrence;
use Austral\LogBundle\Tests\Fixtures\ExtensionStub;
use Austral\LogBundle\Tests\Fixtures\FakeMailer;
use Austral\LogBundle\Tests\Fixtures\LogKit;
use Austral\LogBundle\Tests\Fixtures\SingleManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

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
    self::assertTrue($container->getAlias('austral.entity_manager.error_log')->isPublic());
    self::assertTrue($container->getAlias('austral.log.status_manager')->isPublic());
    self::assertTrue($container->getAlias('austral.log.purger')->isPublic());
    self::assertSame([['command' => 'austral:log:purge']], $container->getDefinition(PurgeCommand::class)->getTag('console.command'));
  }

  public function testTheParameters(): void
  {
    $container = $this->load(['level' => 'critical', 'capture_client_errors' => [404], 'connection' => 'logs', 'occurrences_limit' => 10, 'purge' => ['after_days' => 30, 'statuses' => ['archived']]]);

    self::assertSame(ErrorLog::class, $container->getParameter('austral.entity.error_log.class'));
    self::assertSame(ErrorOccurrence::class, $container->getParameter('austral.entity.error_occurrence.class'));
    self::assertSame('critical', $container->getParameter('austral_log.level'));
    self::assertSame([404], $container->getParameter('austral_log.capture_client_errors'));
    self::assertSame('logs', $container->getParameter('austral_log.connection'));
    self::assertSame(10, $container->getParameter('austral_log.occurrences_limit'));
    self::assertSame(30, $container->getParameter('austral_log.purge.after_days'));
    self::assertSame(['archived'], $container->getParameter('austral_log.purge.statuses'));
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
    $container->register('doctrine.orm.entity_manager', EntityManagerInterface::class)->setSynthetic(true);
    $container->register('event_dispatcher', EventDispatcher::class)->setSynthetic(true);
    $container->setAlias('test.monolog_handler', ErrorLogHandler::class)->setPublic(true);
    $container->addCompilerPass(new LogConnectionPass());
    $container->compile();
    $kit = new LogKit();
    $container->set('doctrine', new SingleManagerRegistry($kit->doctrine));
    $container->set('doctrine.orm.entity_manager', $kit->doctrine);
    $container->set('event_dispatcher', $kit->dispatcher);

    self::assertInstanceOf(ErrorRecorder::class, $container->get('austral.log.recorder'));
    self::assertInstanceOf(ErrorLogHandler::class, $container->get('test.monolog_handler'));
    self::assertNull($container->get('austral.log.recorder')->getLastFailure(), 'Nothing is opened before an error.');
    self::assertInstanceOf(ErrorLogEntityManager::class, $container->get('austral.entity_manager.error_log'));
    self::assertSame(ErrorLog::class, $container->get('austral.entity_manager.error_log')->getClass());
    self::assertInstanceOf(StatusManager::class, $container->get('austral.log.status_manager'));
    self::assertInstanceOf(ErrorPurger::class, $container->get('austral.log.purger'));
  }

  public function testNoReportByDefault(): void
  {
    $container = $this->load();

    self::assertFalse($container->hasDefinition(ErrorReporter::class));
    self::assertFalse($container->hasDefinition(ReportCommand::class));
    self::assertFalse($container->hasAlias('austral.log.reporter'));
  }

  public function testTheReport(): void
  {
    $container = $this->load(['report' => ['enabled' => true, 'frequency' => 'weekly', 'recipients' => ['dev@austral.dev'], 'from' => 'no-reply@austral.dev', 'subject' => 'Errors', 'limit' => 20]]);

    self::assertSame('weekly', $container->getParameter('austral_log.report.frequency'));
    self::assertSame(['dev@austral.dev'], $container->getParameter('austral_log.report.recipients'));
    self::assertSame('no-reply@austral.dev', $container->getParameter('austral_log.report.from'));
    self::assertSame('Errors', $container->getParameter('austral_log.report.subject'));
    self::assertSame(20, $container->getParameter('austral_log.report.limit'));
    self::assertTrue($container->getAlias('austral.log.reporter')->isPublic());
    self::assertSame([['command' => 'austral:log:report']], $container->getDefinition(ReportCommand::class)->getTag('console.command'));
    foreach ([ErrorReporter::class, ReportCommand::class] as $id)
    {
      self::assertFalse($container->getDefinition($id)->isAutowired());
      self::assertFalse($container->getDefinition($id)->isPublic());
    }
  }

  public function testTheReportIsBuilt(): void
  {
    $kit = new LogKit();
    $container = $this->load(['report' => ['enabled' => true, 'recipients' => ['dev@austral.dev'], 'from' => 'no-reply@austral.dev']]);
    foreach (['doctrine' => SingleManagerRegistry::class, 'mailer' => FakeMailer::class, 'twig' => Environment::class] as $id => $class)
    {
      $container->register($id, $class)->setSynthetic(true);
    }
    $container->setAlias('test.reporter', ErrorReporter::class)->setPublic(true);
    $container->setAlias('test.report_command', ReportCommand::class)->setPublic(true);
    $container->setDefinition('doctrine.dbal.connection', new Definition(Connection::class))->setFactory([DriverManager::class, 'getConnection'])->setAbstract(true);
    $container->setDefinition('doctrine.dbal.default_connection', new ChildDefinition('doctrine.dbal.connection'))->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
    $container->register('doctrine.orm.entity_manager', EntityManagerInterface::class)->setSynthetic(true);
    $container->register('event_dispatcher', EventDispatcher::class)->setSynthetic(true);
    $container->addCompilerPass(new LogConnectionPass());
    $container->compile();
    $container->set('doctrine', $kit->registry);
    $container->set('mailer', new FakeMailer());
    $container->set('twig', LogKit::twig());
    $container->set('doctrine.orm.entity_manager', $kit->doctrine);
    $container->set('event_dispatcher', $kit->dispatcher);

    self::assertInstanceOf(ErrorReporter::class, $container->get('test.reporter'));
    self::assertInstanceOf(ReportCommand::class, $container->get('test.report_command'));
  }

  public function testNoBridgeWithoutTheAdmin(): void
  {
    $container = $this->load();

    self::assertFalse($container->hasDefinition(ErrorLogAdmin::class));
    self::assertFalse($container->hasDefinition(ConfigurationCheckListener::class));
  }

  public function testTheBridgeWithTheAdmin(): void
  {
    $container = new ContainerBuilder();
    $container->setParameter('kernel.project_dir', LogKit::PROJECT_DIR);
    $container->setParameter('kernel.environment', 'test');
    $container->setParameter('kernel.bundles', ['AustralAdminBundle' => AustralAdminBundle::class]);
    new AustralLogExtension()->load([self::CONFIG], $container);

    self::assertSame([[]], $container->getDefinition(ErrorLogAdmin::class)->getTag('austral.admin'));
    self::assertSame([['event' => 'austral.admin.configuration_check']], $container->getDefinition(ConfigurationCheckListener::class)->getTag('kernel.event_listener'));
    foreach ([ErrorLogAdmin::class, ConfigurationCheckListener::class] as $id)
    {
      self::assertFalse($container->getDefinition($id)->isAutowired());
      self::assertFalse($container->getDefinition($id)->isPublic());
    }
    self::assertTrue($container->getParameter('austral_log.enabled'));
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
