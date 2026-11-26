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

namespace Austral\LogBundle\Tests\DependencyInjection\Compiler;

use Austral\LogBundle\DependencyInjection\Compiler\LogConnectionPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class LogConnectionPassTest extends TestCase
{
  public function testTheConnectionIsACopyOfTheConnectionOfTheTables(): void
  {
    $container = $this->container('logs');
    $source = new ChildDefinition('doctrine.dbal.connection');
    $source->setArguments([['url' => '%env(DATABASE_URL)%'], new Reference('doctrine.dbal.logs_connection.configuration'), []])
      ->setPublic(true)
      ->addTag('kernel.reset', ['method' => 'close']);
    $container->setDefinition('doctrine.dbal.logs_connection', $source);

    new LogConnectionPass()->process($container);

    $connection = $container->getDefinition(LogConnectionPass::CONNECTION);
    self::assertInstanceOf(ChildDefinition::class, $connection);
    self::assertSame('doctrine.dbal.connection', $connection->getParent());
    self::assertEquals($source->getArguments(), $connection->getArguments(), 'Same parameters, same configuration (middlewares).');
    self::assertNotSame($source, $connection, 'Another service, so another link to the database.');
    self::assertFalse($connection->isPublic());
    self::assertSame([], $connection->getTags());
    self::assertSame([], array_intersect_key($connection->getChanges(), ['class' => true]), 'The class of the parent is kept.');
  }

  public function testTheWrapperClassIsKept(): void
  {
    $container = $this->container('default');
    $container->setDefinition('doctrine.dbal.default_connection', new ChildDefinition('doctrine.dbal.connection'))->setClass('App\Doctrine\Connection');

    new LogConnectionPass()->process($container);

    self::assertSame('App\Doctrine\Connection', $container->getDefinition(LogConnectionPass::CONNECTION)->getClass());
  }

  public function testAnUnknownConnectionIsRefused(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The Doctrine connection "logs" of "austral_log.connection" does not exist.');

    new LogConnectionPass()->process($this->container('logs'));
  }

  public function testNothingWithoutTheBundle(): void
  {
    $container = new ContainerBuilder();
    new LogConnectionPass()->process($container);

    self::assertFalse($container->hasDefinition(LogConnectionPass::CONNECTION));
  }

  private function container(string $connection): ContainerBuilder
  {
    $container = new ContainerBuilder();
    $container->setParameter('austral_log.connection', $connection);

    return $container;
  }
}
