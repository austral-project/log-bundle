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

namespace Austral\LogBundle\Tests\DependencyInjection;

use Austral\LogBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
  public function testTheDefaultValues(): void
  {
    self::assertSame([
      'enabled' => true,
      'level' => 'error',
      'capture_client_errors' => [],
      'connection' => 'default',
      'occurrences_limit' => 50,
      'entities' => [
        'error' => 'App\Entity\Austral\LogBundle\ErrorLog',
        'occurrence' => 'App\Entity\Austral\LogBundle\ErrorOccurrence',
      ],
    ], $this->process([]));
  }

  public function testTheValuesOfTheApplication(): void
  {
    $config = $this->process([
      'enabled' => false,
      'level' => 'critical',
      'capture_client_errors' => [404, 403, 404],
      'connection' => 'logs',
      'occurrences_limit' => 10,
      'entities' => ['error' => 'App\Entity\Error'],
    ]);

    self::assertFalse($config['enabled']);
    self::assertSame('critical', $config['level']);
    self::assertSame([404, 403], $config['capture_client_errors'], 'Each code once.');
    self::assertSame('logs', $config['connection']);
    self::assertSame(10, $config['occurrences_limit']);
    self::assertSame('App\Entity\Error', $config['entities']['error']);
    self::assertSame('App\Entity\Austral\LogBundle\ErrorOccurrence', $config['entities']['occurrence']);
  }

  public function testALevelLowerThanErrorIsRefused(): void
  {
    $this->expectException(InvalidConfigurationException::class);
    $this->process(['level' => 'warning']);
  }

  /**
   * @return iterable<string, array{int}>
   */
  public static function notClientErrors(): iterable
  {
    yield '3xx' => [302];
    yield '5xx' => [500];
  }

  #[DataProvider('notClientErrors')]
  public function testOnlyClientErrorCodes(int $code): void
  {
    $this->expectException(InvalidConfigurationException::class);
    $this->process(['capture_client_errors' => [$code]]);
  }

  public function testAtLeastOneOccurrenceIsKept(): void
  {
    $this->expectException(InvalidConfigurationException::class);
    $this->process(['occurrences_limit' => 0]);
  }

  /**
   * @param array<string, mixed> $config
   *
   * @return array<string, mixed>
   */
  private function process(array $config): array
  {
    return new Processor()->processConfiguration(new Configuration(), [$config]);
  }
}
