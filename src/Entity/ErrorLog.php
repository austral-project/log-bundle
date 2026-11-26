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
use Austral\LogBundle\Enum\ErrorStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Austral Error Log.
 *
 * One line by error: the occurrences with the same fingerprint are grouped. The rows are written by the
 * ErrorRecorder (DBAL), so the names of the columns are fixed: an application can add columns, not rename these.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @extends Entity<object>
 */
#[ORM\MappedSuperclass]
abstract class ErrorLog extends Entity implements ErrorLogInterface
{
  /**
   * @var string
   */
  #[ORM\Column(name: 'id', type: Types::STRING, length: 40)]
  #[ORM\Id]
  protected string $id;

  /**
   * @var string
   */
  #[ORM\Column(name: 'fingerprint', type: Types::STRING, length: 64, unique: true)]
  protected string $fingerprint = '';

  /**
   * @var int
   */
  #[ORM\Column(name: 'level', type: Types::SMALLINT)]
  protected int $level = 400;

  /**
   * @var string
   */
  #[ORM\Column(name: 'channel', type: Types::STRING, length: 64)]
  protected string $channel = 'app';

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'exception_class', type: Types::STRING, length: 255, nullable: true)]
  protected ?string $exceptionClass = null;

  /**
   * @var string
   */
  #[ORM\Column(name: 'message', type: Types::TEXT)]
  protected string $message = '';

  /**
   * @var string|null
   */
  #[ORM\Column(name: 'file', type: Types::STRING, length: 1024, nullable: true)]
  protected ?string $file = null;

  /**
   * @var int|null
   */
  #[ORM\Column(name: 'line', type: Types::INTEGER, nullable: true)]
  protected ?int $line = null;

  /**
   * @var ErrorStatus
   */
  #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: ErrorStatus::class)]
  protected ErrorStatus $status = ErrorStatus::New;

  /**
   * @var int
   */
  #[ORM\Column(name: 'occurrences', type: Types::INTEGER)]
  protected int $occurrences = 0;

  /**
   * @var \DateTimeImmutable
   */
  #[ORM\Column(name: 'first_seen', type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $firstSeen;

  /**
   * @var \DateTimeImmutable
   */
  #[ORM\Column(name: 'last_seen', type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $lastSeen;

  /**
   * @var \DateTimeImmutable|null
   */
  #[ORM\Column(name: 'regressed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  protected ?\DateTimeImmutable $regressedAt = null;

  /**
   * @var \DateTimeImmutable|null
   */
  #[ORM\Column(name: 'status_changed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  protected ?\DateTimeImmutable $statusChangedAt = null;

  /**
   * @var Collection<int, ErrorOccurrenceInterface>
   */
  #[ORM\OneToMany(targetEntity: ErrorOccurrenceInterface::class, mappedBy: 'error', fetch: 'EXTRA_LAZY')]
  #[ORM\OrderBy(['id' => 'DESC'])]
  protected Collection $occurrenceList;

  /**
   * ErrorLog constructor
   */
  public function __construct()
  {
    parent::__construct();
    $this->id = Uuid::v7()->toRfc4122();
    $this->firstSeen = new \DateTimeImmutable();
    $this->lastSeen = $this->firstSeen;
    $this->occurrenceList = new ArrayCollection();
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
      'status' => $this->status->value,
      'level' => $this->level,
      'channel' => $this->channel,
      'exceptionClass' => $this->exceptionClass,
      'message' => $this->message,
      'file' => $this->file,
      'line' => $this->line,
      'occurrences' => $this->occurrences,
      'firstSeen' => $this->firstSeen->format(\DATE_ATOM),
      'lastSeen' => $this->lastSeen->format(\DATE_ATOM),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFingerprint(): string
  {
    return $this->fingerprint;
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
  public function getChannel(): string
  {
    return $this->channel;
  }

  /**
   * {@inheritDoc}
   */
  public function getExceptionClass(): ?string
  {
    return $this->exceptionClass;
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
  public function getFile(): ?string
  {
    return $this->file;
  }

  /**
   * {@inheritDoc}
   */
  public function getLine(): ?int
  {
    return $this->line;
  }

  /**
   * {@inheritDoc}
   */
  public function getStatus(): ErrorStatus
  {
    return $this->status;
  }

  /**
   * {@inheritDoc}
   */
  public function changeStatus(ErrorStatus $status, \DateTimeImmutable $changedAt): bool
  {
    if ($status === $this->status)
    {
      return false;
    }
    $this->status = $status;
    $this->statusChangedAt = $changedAt;

    return true;
  }

  /**
   * {@inheritDoc}
   */
  public function getOccurrences(): int
  {
    return $this->occurrences;
  }

  /**
   * {@inheritDoc}
   */
  public function getFirstSeen(): \DateTimeImmutable
  {
    return $this->firstSeen;
  }

  /**
   * {@inheritDoc}
   */
  public function getLastSeen(): \DateTimeImmutable
  {
    return $this->lastSeen;
  }

  /**
   * {@inheritDoc}
   */
  public function getRegressedAt(): ?\DateTimeImmutable
  {
    return $this->regressedAt;
  }

  /**
   * {@inheritDoc}
   */
  public function getStatusChangedAt(): ?\DateTimeImmutable
  {
    return $this->statusChangedAt;
  }

  /**
   * {@inheritDoc}
   */
  public function getOccurrenceList(): Collection
  {
    return $this->occurrenceList;
  }
}
