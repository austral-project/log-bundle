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

namespace Austral\LogBundle\Tests\Fixtures\Security;

use Austral\SecurityBundle\Entity\User as BaseUser;
use Austral\SecurityBundle\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * User of security-bundle, for the tests of the bridge with admin-bundle.
 */
#[ORM\Table(name: 'austral_security_user')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends BaseUser
{
}
