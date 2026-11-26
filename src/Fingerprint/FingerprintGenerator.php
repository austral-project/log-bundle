<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Fingerprint;

/**
 * Austral Fingerprint Generator.
 *
 * Two occurrences of the same error have the same fingerprint: the class of the exception, the file (from the root
 * of the project, so a deployment in another folder keeps the fingerprints), the line and the message without its
 * values. Without exception, the channel replaces the class.
 *
 * A change of line (the file was edited) gives a new fingerprint: the error is then a new one.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class FingerprintGenerator
{
  /**
   * Values replaced in the messages, in this order.
   *
   * @var array<string, string>
   */
  private const array PATTERNS = [
    // "quoted" and 'quoted' values
    '/"(?:[^"\\\\]|\\\\.)*"/u' => '"{s}"',
    "/'(?:[^'\\\\]|\\\\.)*'/u" => "'{s}'",
    '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '{uuid}',
    '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i' => '{email}',
    '/\b[0-9a-f]{16,}\b/i' => '{hex}',
    '/\b\d+(?:[.,]\d+)*\b/' => '{n}',
  ];

  /**
   * FingerprintGenerator constructor
   *
   * @param string $projectDir
   */
  public function __construct(
    private readonly string $projectDir = '',
  ) {
  }

  /**
   * generate
   * SHA-256 of the parts, 64 hexadecimal characters.
   *
   * @param string      $type    class of the exception, or channel of the record
   * @param string|null $file    absolute path or path from the root of the project
   * @param int|null    $line
   * @param string      $message message or template of the record
   *
   * @return string
   */
  public function generate(string $type, ?string $file, ?int $line, string $message): string
  {
    return hash('sha256', implode("\n", [
      $type,
      $this->relativePath($file) ?? '',
      null === $line ? '' : (string) $line,
      $this->normalize($message),
    ]));
  }

  /**
   * normalize
   * The message without its values: "User 12 not found" and "User 13 not found" give the same message.
   *
   * @param string $message
   *
   * @return string
   */
  public function normalize(string $message): string
  {
    $normalized = preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $message);

    return trim(preg_replace('/\s+/u', ' ', $normalized ?? $message) ?? $message);
  }

  /**
   * relativePath
   * The path from the root of the project; the other paths (vendor out of the project...) are kept.
   *
   * @param string|null $file
   *
   * @return string|null
   */
  public function relativePath(?string $file): ?string
  {
    if (null === $file || '' === $file)
    {
      return null;
    }
    $root = rtrim($this->projectDir, '/\\');
    if ('' !== $root && str_starts_with($file, $root.\DIRECTORY_SEPARATOR))
    {
      return substr($file, \strlen($root) + 1);
    }

    return $file;
  }
}
