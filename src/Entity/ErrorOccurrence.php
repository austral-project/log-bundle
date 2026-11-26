<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Entity;

use Austral\EntityBundle\Entity\Entity;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Entity\Interfaces\ErrorOccurrenceInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Austral Error Occurrence.
 *
 * One occurrence of an error, with its context. Only the last ones are kept (austral_log.occurrences_limit). The
 * id is a UUID v7: the ids are in the order of the occurrences. Written by the ErrorRecorder (DBAL), like
 * ErrorLog: the names of the columns are fixed.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @extends Entity<object>
 */
#[ORM\MappedSuperclass]
abstract class ErrorOccurrence extends Entity implements ErrorOccurrenceInterface
{
  /**
   * @var string
   */
  #[ORM\Column(name: 'id', type: Types::STRING, length: 40)]
  #[ORM\Id]
  protected string $id;

  /**
   * @var ErrorLogInterface
   */
  #[ORM\ManyToOne(targetEntity: ErrorLogInterface::class, inversedBy: 'occurrenceList')]
  #[ORM\JoinColumn(name: 'error_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  protected ErrorLogInterface $error;

  /**
   * @var \DateTimeImmutable
   */
  #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $occurredAt;

  /**
   * @var int
   */
  #[ORM\Column(name: 'level', type: Types::SMALLINT)]
  protected int $level = 400;

  /**
   * @var string
   */
  #[ORM\Column(name: 'message', type: Types::TEXT)]
  protected string $message = '';

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'url', type: Types::TEXT, nullable: true)]
  protected ?string $url = null;

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'method', type: Types::STRING, length: 10, nullable: true)]
  protected ?string $method = null;

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'route', type: Types::STRING, length: 255, nullable: true)]
  protected ?string $route = null;

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'user_id', type: Types::STRING, length: 255, nullable: true)]
  protected ?string $userId = null;

  /**
   * @var string
   */
  #[ORM\Column(name: 'environment', type: Types::STRING, length: 32)]
  protected string $environment = '';

  /**
   * @var list<array{class: string, message: string, file: string, line: int, frames: list<array{file: ?string, line: ?int, function: string}>}>
   */
  #[ORM\Column(name: 'trace', type: Types::JSON)]
  protected array $trace = [];

  /**
   * @var array<string, mixed>
   */
  #[ORM\Column(name: 'context', type: Types::JSON)]
  protected array $context = [];

  /**
   * ErrorOccurrence constructor
   */
  public function __construct()
  {
    parent::__construct();
    $this->id = Uuid::v7()->toRfc4122();
    $this->occurredAt = new \DateTimeImmutable();
  }

  /**
   * {@inheritDoc}
   */
  public function __toString(): string
  {
    return $this->message;
  }

  /**
   * arrayObject
   *
   * @return array<string, mixed>
   */
  public function arrayObject(): array
  {
    return [
      'id' => $this->getId(),
      'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
      'level' => $this->level,
      'message' => $this->message,
      'url' => $this->url,
      'method' => $this->method,
      'route' => $this->route,
      'userId' => $this->userId,
      'environment' => $this->environment,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getError(): ErrorLogInterface
  {
    return $this->error;
  }

  /**
   * {@inheritDoc}
   */
  public function getOccurredAt(): \DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * {@inheritDoc}
   */
  public function getLevel(): int
  {
    return $this->level;
  }

  /**
   * {@inheritDoc}
   */
  public function getMessage(): string
  {
    return $this->message;
  }

  /**
   * {@inheritDoc}
   */
  public function getUrl(): ?string
  {
    return $this->url;
  }

  /**
   * {@inheritDoc}
   */
  public function getMethod(): ?string
  {
    return $this->method;
  }

  /**
   * {@inheritDoc}
   */
  public function getRoute(): ?string
  {
    return $this->route;
  }

  /**
   * {@inheritDoc}
   */
  public function getUserId(): ?string
  {
    return $this->userId;
  }

  /**
   * {@inheritDoc}
   */
  public function getEnvironment(): string
  {
    return $this->environment;
  }

  /**
   * {@inheritDoc}
   */
  public function getTrace(): array
  {
    return $this->trace;
  }

  /**
   * {@inheritDoc}
   */
  public function getContext(): array
  {
    return $this->context;
  }
}
