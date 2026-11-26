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

namespace Austral\LogBundle\Tests\Fingerprint;

use Austral\LogBundle\Fingerprint\FingerprintGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FingerprintGeneratorTest extends TestCase
{
  /**
   * @return iterable<string, array{string, string}>
   */
  public static function messages(): iterable
  {
    yield 'numbers' => ['User 12 not found after 3.5 s', 'User {n} not found after {n} s'];
    yield 'double quotes' => ['Entity "App\Entity\Page" not found', 'Entity "{s}" not found'];
    yield 'single quotes' => ["Column 'title' cannot be null", "Column '{s}' cannot be null"];
    yield 'escaped quote' => ['Value "a \" b" refused', 'Value "{s}" refused'];
    yield 'uuid' => ['Page 0190a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b missing', 'Page {uuid} missing'];
    yield 'email' => ['Mail to jane@austral.dev failed', 'Mail to {email} failed'];
    yield 'hexadecimal' => ['Key 0123456789abcdef0123 missing', 'Key {hex} missing'];
    yield 'spaces' => ["Two\n  lines ", 'Two lines'];
    yield 'words with digits' => ['Invalid utf8 in h1', 'Invalid utf8 in h1'];
  }

  #[DataProvider('messages')]
  public function testTheValuesAreRemovedFromTheMessage(string $message, string $normalized): void
  {
    self::assertSame($normalized, new FingerprintGenerator()->normalize($message));
  }

  public function testTheFingerprintDoesNotDependOnTheFolderOfTheDeployment(): void
  {
    $release1 = new FingerprintGenerator('/var/www/releases/1');
    $release2 = new FingerprintGenerator('/var/www/releases/2/');

    self::assertSame(
      $release1->generate(\RuntimeException::class, '/var/www/releases/1/src/Service.php', 12, 'Order 12 failed'),
      $release2->generate(\RuntimeException::class, '/var/www/releases/2/src/Service.php', 12, 'Order 13 failed'),
    );
    self::assertSame('src/Service.php', $release1->relativePath('/var/www/releases/1/src/Service.php'));
    self::assertSame('/var/www/releases/10/src/Service.php', $release1->relativePath('/var/www/releases/10/src/Service.php'), 'Not a folder of the project.');
    self::assertNull($release1->relativePath(null));
    self::assertNull($release1->relativePath(''));
  }

  public function testEachPartChangesTheFingerprint(): void
  {
    $generator = new FingerprintGenerator();
    $fingerprint = $generator->generate('A', 'a.php', 1, 'message');

    self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
    self::assertNotSame($fingerprint, $generator->generate('B', 'a.php', 1, 'message'));
    self::assertNotSame($fingerprint, $generator->generate('A', 'b.php', 1, 'message'));
    self::assertNotSame($fingerprint, $generator->generate('A', 'a.php', 2, 'message'));
    self::assertNotSame($fingerprint, $generator->generate('A', 'a.php', 1, 'other'));
    self::assertNotSame($generator->generate('A', null, null, ''), $generator->generate('A', '', 0, ''));
  }
}
