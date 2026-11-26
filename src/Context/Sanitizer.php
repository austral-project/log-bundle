<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Context;

/**
 * Austral Sanitizer.
 *
 * Turns any value into a value that can be saved in JSON, without the sensitive data: the value of a key whose
 * name looks sensitive (password, token, cookie, authorization...) is replaced by "***", whatever its depth.
 * Objects are replaced by their class, long strings are cut, the depth and the number of items are limited.
 *
 * The messages themselves are not sanitized: a message that contains a secret keeps it.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class Sanitizer
{
  public const string MASK = '***';

  private const string SENSITIVE_KEY = '/pass|pwd|secret|token|authori[sz]ation|bearer|cookie|session|csrf|api[-_]?key|credential|private[-_]?key|php[-_]auth|signature|iban|card[-_]?number|cvv|cvc/i';

  private const int MAX_DEPTH = 6;

  private const int MAX_ITEMS = 100;

  private const int MAX_STRING = 2000;

  /**
   * Sanitizer constructor
   *
   * @param int $maxDepth
   * @param int $maxItems
   * @param int $maxString
   */
  public function __construct(
    private readonly int $maxDepth = self::MAX_DEPTH,
    private readonly int $maxItems = self::MAX_ITEMS,
    private readonly int $maxString = self::MAX_STRING,
  ) {
  }

  /**
   * isSensitiveKey
   *
   * @param string $key
   *
   * @return bool
   */
  public function isSensitiveKey(string $key): bool
  {
    return 1 === preg_match(self::SENSITIVE_KEY, $key);
  }

  /**
   * sanitize
   *
   * @param array<array-key, mixed> $values
   *
   * @return array<array-key, mixed>
   */
  public function sanitize(array $values): array
  {
    $sanitized = $this->value($values, 0);

    return \is_array($sanitized) ? $sanitized : [];
  }

  /**
   * truncate
   * The value in valid UTF-8 (invalid bytes replaced: json_encode() and PostgreSQL refuse them), cut when it is too
   * long.
   *
   * @param string $value
   *
   * @return string
   */
  public function truncate(string $value): string
  {
    $value = mb_scrub($value, 'UTF-8');
    if (mb_strlen($value) <= $this->maxString)
    {
      return $value;
    }

    return mb_substr($value, 0, $this->maxString).'… ['.(mb_strlen($value) - $this->maxString).' more characters]';
  }

  /**
   * value
   *
   * @param mixed $value
   * @param int   $depth
   *
   * @return mixed
   */
  private function value(mixed $value, int $depth): mixed
  {
    return match (true)
    {
      null === $value, \is_bool($value), \is_int($value) => $value,
      \is_float($value) => is_finite($value) ? $value : (string) $value,
      \is_string($value) => $this->truncate($value),
      \is_array($value) => $this->array($value, $depth),
      $value instanceof \UnitEnum => $value::class.'::'.$value->name,
      $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
      $value instanceof \Throwable => $value::class,
      \is_object($value) => 'object('.$value::class.')',
      default => get_debug_type($value),
    };
  }

  /**
   * array
   *
   * @param array<array-key, mixed> $values
   * @param int                     $depth
   *
   * @return array<array-key, mixed>|string
   */
  private function array(array $values, int $depth): array|string
  {
    if ($depth >= $this->maxDepth)
    {
      return 'array('.\count($values).')';
    }
    $sanitized = [];
    $count = 0;
    foreach ($values as $key => $value)
    {
      if (++$count > $this->maxItems)
      {
        $sanitized['…'] = (\count($values) - $this->maxItems).' more items';
        break;
      }
      $sanitized[$key] = \is_string($key) && $this->isSensitiveKey($key) ? self::MASK : $this->value($value, $depth + 1);
    }

    return $sanitized;
  }
}
