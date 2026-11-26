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

use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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
   * The Monolog handler and the interfaces of the associations are added before the configuration of the
   * application: the bundle records the errors without its recipe.
   *
   * @param ContainerBuilder $container
   *
   * @return void
   */
  public function prepend(ContainerBuilder $container): void
  {
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
    $container->setParameter('austral_log.level', $config['level']);
    $container->setParameter('austral_log.capture_client_errors', $config['capture_client_errors']);
    $container->setParameter('austral_log.connection', $config['connection']);
    $container->setParameter('austral_log.occurrences_limit', $config['occurrences_limit']);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.yaml');
  }

  /**
   * @return string
   */
  public function getNamespace(): string
  {
    return 'https://austral.dev/schema/dic/austral_log';
  }
}
