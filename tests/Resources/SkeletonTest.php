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

namespace Austral\LogBundle\Tests\Resources;

use Austral\LogBundle\Tests\Fixtures\LogKit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The entities given to the applications are those of the tests.
 */
final class SkeletonTest extends TestCase
{
  private const string SKELETON = __DIR__.'/../../src/Skeleton/Entity';

  private const string FIXTURES = __DIR__.'/../Fixtures/Entity';

  /**
   * @return iterable<string, array{string}>
   */
  public static function entities(): iterable
  {
    yield 'ErrorLog' => ['ErrorLog'];
    yield 'ErrorOccurrence' => ['ErrorOccurrence'];
  }

  /**
   * Same attributes (table, indexes, repository) as the fixtures, which the tests check on a real database.
   */
  #[DataProvider('entities')]
  public function testTheSkeletonIsTheEntityOfTheTests(string $entity): void
  {
    $skeleton = (string) file_get_contents(self::SKELETON."/{$entity}.php");
    $fixture = (string) file_get_contents(self::FIXTURES."/{$entity}.php");

    self::assertStringContainsString('namespace App\Entity\Austral\LogBundle;', $skeleton);
    self::assertStringContainsString("class {$entity} extends Base{$entity}", $skeleton);
    self::assertSame($this->attributes($fixture), $this->attributes($skeleton));
  }

  public function testTheTablesAndTheirColumns(): void
  {
    $connection = new LogKit()->doctrine->getConnection();

    self::assertSame(['channel', 'exception_class', 'file', 'fingerprint', 'first_seen', 'id', 'last_seen', 'level', 'line', 'message', 'notified_at', 'occurrences', 'regressed_at', 'status', 'status_changed_at'], $this->columns($connection, 'austral_log_error'));
    self::assertSame(['context', 'environment', 'error_id', 'id', 'level', 'message', 'method', 'occurred_at', 'route', 'trace', 'url', 'user_id'], $this->columns($connection, 'austral_log_error_occurrence'));

    $foreignKeys = $connection->fetchAllAssociative("PRAGMA foreign_key_list('austral_log_error_occurrence')");
    self::assertCount(1, $foreignKeys);
    self::assertSame('austral_log_error', $foreignKeys[0]['table']);
    self::assertSame('error_id', $foreignKeys[0]['from']);
    self::assertSame('CASCADE', $foreignKeys[0]['on_delete'], 'Deleting an error deletes its occurrences.');
  }

  /**
   * The columns of a table of the SQLite database of the tests, sorted.
   *
   * @return list<string>
   */
  private function columns(Connection $connection, string $table): array
  {
    $columns = array_map(static fn (array $column): string => (string) $column['name'], $connection->fetchAllAssociative("PRAGMA table_info('{$table}')"));
    sort($columns);

    return $columns;
  }

  /**
   * @return list<string>
   */
  private function attributes(string $source): array
  {
    preg_match_all('/^#\[ORM\\\\.+$/m', $source, $matches);

    return $matches[0];
  }
}
