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

namespace Austral\LogBundle\Tests\Context;

use Austral\LogBundle\Context\Sanitizer;
use Austral\LogBundle\Enum\ErrorStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function keys(): iterable
  {
    foreach (['password', 'plainPassword', '_password', 'pwd', 'client_secret', 'token', '_csrf_token', 'authorization', 'cookie', 'PHPSESSID_session', 'x-api-key', 'apiKey', 'credentials', 'private_key', 'php-auth-pw', 'signature', 'iban', 'card_number', 'cvv'] as $key)
    {
      yield $key => [$key, true];
    }
    foreach (['id', 'email', 'author', 'route', 'page', 'command', 'cardinality', 'level'] as $key)
    {
      yield $key => [$key, false];
    }
  }

  #[DataProvider('keys')]
  public function testTheSensitiveKeys(string $key, bool $sensitive): void
  {
    self::assertSame($sensitive, new Sanitizer()->isSensitiveKey($key));
  }

  public function testTheSensitiveValuesAreMaskedAtAnyDepth(): void
  {
    $sanitized = new Sanitizer()->sanitize([
      'user' => ['email' => 'jane@austral.dev', 'password' => 'secret', 'tokens' => ['a', 'b']],
      'headers' => ['cookie' => ['PHPSESSID=abc']],
      'id' => 3,
    ]);

    self::assertSame([
      'user' => ['email' => 'jane@austral.dev', 'password' => Sanitizer::MASK, 'tokens' => Sanitizer::MASK],
      'headers' => ['cookie' => Sanitizer::MASK],
      'id' => 3,
    ], $sanitized);
  }

  public function testEveryValueCanBeSavedInJson(): void
  {
    $resource = fopen('php://memory', 'r');
    $sanitized = new Sanitizer()->sanitize([
      'null' => null,
      'bool' => true,
      'float' => 1.5,
      'infinite' => \INF,
      'binary' => "\xff\xfe",
      'date' => new \DateTimeImmutable('2026-11-26 10:00:00+00:00'),
      'enum' => ErrorStatus::New,
      'exception' => new \RuntimeException('Failed'),
      'object' => new \stdClass(),
      'resource' => $resource,
    ]);

    self::assertNull($sanitized['null']);
    self::assertTrue($sanitized['bool']);
    self::assertSame(1.5, $sanitized['float']);
    self::assertSame('INF', $sanitized['infinite']);
    self::assertTrue(mb_check_encoding($sanitized['binary'], 'UTF-8'));
    self::assertSame('2026-11-26T10:00:00+00:00', $sanitized['date']);
    self::assertSame(ErrorStatus::class.'::New', $sanitized['enum']);
    self::assertSame(\RuntimeException::class, $sanitized['exception']);
    self::assertSame('object(stdClass)', $sanitized['object']);
    self::assertSame('resource (stream)', $sanitized['resource']);
    self::assertIsString(json_encode($sanitized, \JSON_THROW_ON_ERROR));
  }

  public function testTheSizeIsLimited(): void
  {
    $sanitizer = new Sanitizer(maxDepth: 2, maxItems: 3, maxString: 10);

    self::assertSame(['a' => ['b' => 'array(1)']], $sanitizer->sanitize(['a' => ['b' => ['c' => 1]]]));
    self::assertSame([1, 2, 3, '…' => '2 more items'], $sanitizer->sanitize([1, 2, 3, 4, 5]));
    self::assertSame('0123456789… [5 more characters]', $sanitizer->truncate('012345678901234'));
    self::assertSame('short', $sanitizer->truncate('short'));
  }
}
