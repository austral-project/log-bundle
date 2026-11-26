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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension of another bundle (doctrine, monolog), to read what prepend() gives it.
 */
final class ExtensionStub extends Extension
{
  public function __construct(private readonly string $alias)
  {
  }

  public function getAlias(): string
  {
    return $this->alias;
  }

  /**
   * @param array<array-key, mixed> $configs
   */
  public function load(array $configs, ContainerBuilder $container): void
  {
  }
}
