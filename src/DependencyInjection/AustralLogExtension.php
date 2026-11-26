<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\DependencyInjection;

use Austral\AdminBundle\DependencyInjection\AdminModulesPrepender;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

/**
 * Austral Log Extension.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class AustralLogExtension extends Extension implements PrependExtensionInterface
{
  /**
   * Id of the Monolog handler added to monolog by prepend().
   */
  public const string MONOLOG_HANDLER = 'austral_log';

  /**
   * {@inheritDoc}
   */
  public function prepend(ContainerBuilder $container): void
  {
    // The Monolog handler, the interfaces of the associations and the module of the admin are added before the
    // configuration of the application: the bundle records the errors without its recipe.
    if (class_exists(AdminModulesPrepender::class))
    {
      AdminModulesPrepender::prepend($container, __DIR__.'/../Bridge/Admin/austral_admin.yaml');
    }
    $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));

    if ($container->hasExtension('doctrine'))
    {
      $container->prependExtensionConfig('doctrine', [
        'orm' => [
          'resolve_target_entities' => [
            ErrorLogInterface::class => $config['entities']['error'],
            ErrorOccurrenceInterface::class => $config['entities']['occurrence'],
          ],
        ],
      ]);
    }

    // A handler of type "service" receives the records of all the channels, whatever the other handlers
    // (fingers_crossed...) of the application. Its level is set on the service (load()).
    if ($config['enabled'] && $container->hasExtension('monolog'))
    {
      $container->prependExtensionConfig('monolog', [
        'handlers' => [
          self::MONOLOG_HANDLER => [
            'type' => 'service',
            'id' => 'austral.log.monolog_handler',
          ],
        ],
      ]);
    }
  }

  /**
   * load
   *
   * @param array<array-key, mixed> $configs
   * @param ContainerBuilder        $container
   *
   * @return void
   */
  public function load(array $configs, ContainerBuilder $container): void
  {
    $config = $this->processConfiguration(new Configuration(), $configs);
    $container->setParameter('austral.entity.error_log.class', $config['entities']['error']);
    $container->setParameter('austral.entity.error_occurrence.class', $config['entities']['occurrence']);
    $container->setParameter('austral_log.enabled', $config['enabled']);
    $container->setParameter('austral_log.level', $config['level']);
    $container->setParameter('austral_log.capture_client_errors', $config['capture_client_errors']);
    $container->setParameter('austral_log.connection', $config['connection']);
    $container->setParameter('austral_log.occurrences_limit', $config['occurrences_limit']);
    $container->setParameter('austral_log.purge.after_days', $config['purge']['after_days']);
    $container->setParameter('austral_log.purge.statuses', $config['purge']['statuses']);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.yaml');
    if (self::isBundleEnabled($container, 'AustralAdminBundle'))
    {
      $loader->load('bridge/admin.yaml');
    }

    if ($config['report']['enabled'])
    {
      if (!interface_exists(MailerInterface::class) || !class_exists(Environment::class))
      {
        throw new \LogicException('"austral_log.report" needs symfony/mailer and twig/twig: composer require symfony/mailer twig/twig');
      }
      $container->setParameter('austral_log.report.frequency', $config['report']['frequency']);
      $container->setParameter('austral_log.report.recipients', $config['report']['recipients']);
      $container->setParameter('austral_log.report.from', $config['report']['from']);
      $container->setParameter('austral_log.report.subject', $config['report']['subject']);
      $container->setParameter('austral_log.report.limit', $config['report']['limit']);
      $loader->load('report.yaml');
    }
  }

  /**
   * isBundleEnabled
   *
   * @param ContainerBuilder $container
   * @param string           $name      name of the bundle in kernel.bundles
   *
   * @return bool
   */
  private static function isBundleEnabled(ContainerBuilder $container, string $name): bool
  {
    $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

    return \is_array($bundles) && isset($bundles[$name]);
  }

  /**
   * {@inheritDoc}
   */
  public function getNamespace(): string
  {
    return 'https://austral.dev/schema/dic/austral_log';
  }
}
