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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Austral Log Configuration.
 *
 * austral_log:
 *   enabled:            records the errors (false: the Monolog handler is not added)
 *   level:              lowest Monolog level recorded
 *   capture_client_errors: HTTP codes of the visitors' errors (4xx) recorded too, none by default
 *   connection:         Doctrine connection of the tables; the errors are written by a second connection
 *   occurrences_limit:  occurrences kept by error (the last ones)
 *   entities:           classes of the application (error, occurrence)
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class Configuration implements ConfigurationInterface
{
  public const array LEVELS = ['error', 'critical', 'alert', 'emergency'];

  /**
   * @return TreeBuilder
   */
  public function getConfigTreeBuilder(): TreeBuilder
  {
    $treeBuilder = new TreeBuilder('austral_log');
    $treeBuilder->getRootNode()
      ->addDefaultsIfNotSet()
      ->children()
        ->booleanNode('enabled')->defaultTrue()->end()
        ->enumNode('level')->values(self::LEVELS)->defaultValue('error')->info('Lowest Monolog level recorded')->end()
        ->arrayNode('capture_client_errors')
          ->info('HTTP codes of the errors of the visitors (4xx) recorded too, e.g. [404, 403]; the 5xx are always recorded')
          ->integerPrototype()->min(400)->max(499)->end()
          ->validate()
            ->always(static fn (array $codes): array => array_values(array_unique($codes)))
          ->end()
        ->end()
        ->scalarNode('connection')->cannotBeEmpty()->defaultValue('default')->info('Doctrine connection of the tables of the errors')->end()
        ->integerNode('occurrences_limit')->min(1)->max(1000)->defaultValue(50)->info('Occurrences kept by error, the last ones')->end()
        ->arrayNode('entities')
          ->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('error')->cannotBeEmpty()->defaultValue('App\Entity\Austral\LogBundle\ErrorLog')->end()
            ->scalarNode('occurrence')->cannotBeEmpty()->defaultValue('App\Entity\Austral\LogBundle\ErrorOccurrence')->end()
          ->end()
        ->end()
      ->end();

    return $treeBuilder;
  }
}
