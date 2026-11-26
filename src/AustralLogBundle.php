<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle;

use Austral\LogBundle\DependencyInjection\Compiler\LogConnectionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Austral Log Bundle.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class AustralLogBundle extends Bundle
{
  /**
   * {@inheritDoc}
   */
  public function build(ContainerBuilder $container): void
  {
    parent::build($container);
    $container->addCompilerPass(new LogConnectionPass());
  }
}
