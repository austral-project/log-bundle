<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Second Doctrine connection of log-bundle.
 *
 * "austral.log.connection" is built like the connection given by "austral_log.connection" (same parameters, same
 * configuration and middlewares), but it is another service, so another link to the database: an error is
 * recorded even when the transaction of the application is rolled back. It connects only when an error is
 * recorded.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class LogConnectionPass implements CompilerPassInterface
{
  public const string CONNECTION = 'austral.log.connection';

  /**
   * {@inheritDoc}
   */
  public function process(ContainerBuilder $container): void
  {
    if (!$container->hasParameter('austral_log.connection'))
    {
      return;
    }
    $name = $container->getParameter('austral_log.connection');
    $sourceId = \sprintf('doctrine.dbal.%s_connection', \is_string($name) ? $name : '');
    if (!$container->hasDefinition($sourceId))
    {
      throw new InvalidArgumentException(\sprintf('The Doctrine connection "%s" of "austral_log.connection" does not exist.', \is_string($name) ? $name : get_debug_type($name)));
    }

    $source = $container->getDefinition($sourceId);
    $definition = $source instanceof ChildDefinition ? new ChildDefinition($source->getParent()) : clone $source;
    $definition
      ->setArguments($source->getArguments())
      ->setPublic(false)
      ->setTags([]);
    // On a ChildDefinition, setClass(null) would replace the class of the parent: only the "wrapper_class" is copied.
    if (null !== $source->getClass())
    {
      $definition->setClass($source->getClass());
    }
    $container->setDefinition(self::CONNECTION, $definition);
  }
}
