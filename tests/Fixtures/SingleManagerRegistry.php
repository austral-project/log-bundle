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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;

/**
 * The "doctrine" service of the tests: one entity manager.
 */
final class SingleManagerRegistry extends AbstractManagerRegistry
{
  public function __construct(private readonly EntityManagerInterface $manager)
  {
    parent::__construct('ORM', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', Proxy::class);
  }

  protected function getService(string $name): object
  {
    return 'connection' === $name ? $this->manager->getConnection() : $this->manager;
  }

  protected function resetService(string $name): void
  {
  }
}
