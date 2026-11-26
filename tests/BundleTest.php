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

namespace Austral\LogBundle\Tests;

use Austral\LogBundle\AustralLogBundle;
use Austral\LogBundle\DependencyInjection\AustralLogExtension;
use Austral\LogBundle\DependencyInjection\Compiler\LogConnectionPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BundleTest extends TestCase
{
  public function testTheBundleAddsItsExtensionAndItsConnection(): void
  {
    $bundle = new AustralLogBundle();
    $container = new ContainerBuilder();
    $bundle->build($container);

    self::assertInstanceOf(AustralLogExtension::class, $bundle->getContainerExtension());
    self::assertSame('austral_log', $bundle->getContainerExtension()->getAlias());
    $passes = array_filter($container->getCompilerPassConfig()->getPasses(), static fn ($pass): bool => $pass instanceof LogConnectionPass);
    self::assertCount(1, $passes);
  }
}
