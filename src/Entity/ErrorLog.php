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
  #[ORM\Column(name: 'id', type: Types::STRING, length: 40)]
  #[ORM\Id]
  protected string $id;

  #[ORM\Column(name: 'fingerprint', type: Types::STRING, length: 64, unique: true)]
  protected string $fingerprint = '';

  #[ORM\Column(name: 'level', type: Types::SMALLINT)]
  protected int $level = 400;

  #[ORM\Column(name: 'channel', type: Types::STRING, length: 64)]
  protected string $channel = 'app';

  #[ORM\Column(name: 'exception_class', type: Types::STRING, length: 255, nullable: true)]
  protected ?string $exceptionClass = null;

  #[ORM\Column(name: 'message', type: Types::TEXT)]
  protected string $message = '';

  #[ORM\Column(name: 'file', type: Types::STRING, length: 1024, nullable: true)]
  protected ?string $file = null;

  #[ORM\Column(name: 'line', type: Types::INTEGER, nullable: true)]
  protected ?int $line = null;

  #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: ErrorStatus::class)]
  protected ErrorStatus $status = ErrorStatus::New;

  #[ORM\Column(name: 'occurrences', type: Types::INTEGER)]
  protected int $occurrences = 0;

  #[ORM\Column(name: 'first_seen', type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $firstSeen;

  #[ORM\Column(name: 'last_seen', type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $lastSeen;

  #[ORM\Column(name: 'regressed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  protected ?\DateTimeImmutable $regressedAt = null;

  #[ORM\Column(name: 'status_changed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
  protected ?\DateTimeImmutable $statusChangedAt = null;

  /**
   * @var Collection<int, ErrorOccurrenceInterface>
   */
  #[ORM\OneToMany(targetEntity: ErrorOccurrenceInterface::class, mappedBy: 'error', fetch: 'EXTRA_LAZY')]
  #[ORM\OrderBy(['id' => 'DESC'])]
  protected Collection $occurrenceList;

  public function __construct()
  {
    parent::__construct();
    $this->id = Uuid::v7()->toRfc4122();
    $this->firstSeen = new \DateTimeImmutable();
    $this->lastSeen = $this->firstSeen;
    $this->occurrenceList = new ArrayCollection();
  }

  public function __toString(): string
  {
    return $this->message;
  }

  /**
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

  public function getFingerprint(): string
  {
    return $this->fingerprint;
  }

  public function getLevel(): int
  {
    return $this->level;
  }

  public function getChannel(): string
  {
    return $this->channel;
  }

  public function getExceptionClass(): ?string
  {
    return $this->exceptionClass;
  }

  public function getMessage(): string
  {
    return $this->message;
  }

  public function getFile(): ?string
  {
    return $this->file;
  }

  public function getLine(): ?int
  {
    return $this->line;
  }

  public function getStatus(): ErrorStatus
  {
    return $this->status;
  }

  public function getOccurrences(): int
  {
    return $this->occurrences;
  }

  public function getFirstSeen(): \DateTimeImmutable
  {
    return $this->firstSeen;
  }

  public function getLastSeen(): \DateTimeImmutable
  {
    return $this->lastSeen;
  }

  public function getRegressedAt(): ?\DateTimeImmutable
  {
    return $this->regressedAt;
  }

  public function getStatusChangedAt(): ?\DateTimeImmutable
  {
    return $this->statusChangedAt;
  }

  /**
   * @return Collection<int, ErrorOccurrenceInterface>
   */
  public function getOccurrenceList(): Collection
  {
    return $this->occurrenceList;
  }
}
