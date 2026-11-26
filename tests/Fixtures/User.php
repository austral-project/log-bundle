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

use Austral\EntityBundle\Entity\Entity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A logged in user that is an Austral entity, as the users of security-bundle.
 *
 * @extends Entity<object>
 */
final class User extends Entity implements UserInterface
{
  public function __construct()
  {
    parent::__construct();
    $this->id = Uuid::v7()->toRfc4122();
  }

  public function getRoles(): array
  {
    return ['ROLE_USER'];
  }

  public function getUserIdentifier(): string
  {
    return 'jane@austral.dev';
  }

  #[\Deprecated]
  public function eraseCredentials(): void
  {
  }
}
