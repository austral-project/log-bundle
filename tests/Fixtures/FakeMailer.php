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

namespace Austral\LogBundle\Tests\Fixtures;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer that keeps the emails, or fails when asked.
 */
final class FakeMailer implements MailerInterface
{
  /**
   * @var list<Email>
   */
  public array $sent = [];

  public bool $fail = false;

  public function send(RawMessage $message, ?Envelope $envelope = null): void
  {
    if ($this->fail)
    {
      throw new TransportException('The SMTP server does not answer.');
    }
    if ($message instanceof Email)
    {
      $this->sent[] = $message;
    }
  }
}
