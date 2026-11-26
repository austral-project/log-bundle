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
 *   purge:              austral:log:purge deletes the resolved and archived errors after X days
 *   report:             austral:log:report sends the new errors and the regressions by email (symfony/mailer, twig)
 *   entities:           classes of the application (error, occurrence)
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class Configuration implements ConfigurationInterface
{
  public const array LEVELS = ['error', 'critical', 'alert', 'emergency'];

  /**
   * {@inheritDoc}
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
        ->arrayNode('purge')
          ->addDefaultsIfNotSet()
          ->children()
            ->integerNode('after_days')->min(0)->defaultValue(90)->info('Days since the change of the status; 0: no purge')->end()
            ->arrayNode('statuses')
              ->info('Statuses purged; the new, in progress and ignored errors are never purged')
              ->enumPrototype()->values(['resolved', 'archived'])->end()
              ->defaultValue(['resolved', 'archived'])
              ->requiresAtLeastOneElement()
              ->validate()
                ->always(static fn (array $statuses): array => array_values(array_unique($statuses)))
              ->end()
            ->end()
          ->end()
        ->end()
        ->arrayNode('report')
          ->addDefaultsIfNotSet()
          ->children()
            ->booleanNode('enabled')->defaultFalse()->info('Defines austral:log:report; needs symfony/mailer and twig/twig')->end()
            ->enumNode('frequency')->values(['hourly', 'daily', 'weekly'])->defaultValue('daily')->info('At most one report by period; run the command every hour')->end()
            ->arrayNode('recipients')
              ->info('Email addresses of the report')
              ->scalarPrototype()->cannotBeEmpty()->end()
            ->end()
            ->scalarNode('from')->defaultNull()->info('Sender of the report')->end()
            ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Errors of the site')->end()
            ->integerNode('limit')->min(1)->max(500)->defaultValue(50)->info('Errors listed in the email; the others are counted')->end()
          ->end()
          ->validate()
            ->ifTrue(static fn (array $report): bool => $report['enabled'] && ([] === $report['recipients'] || !\is_string($report['from']) || '' === $report['from']))
            ->thenInvalid('"austral_log.report" needs "recipients" and "from" when it is enabled.')
          ->end()
        ->end()
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
