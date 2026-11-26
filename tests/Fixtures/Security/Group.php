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

use Austral\SecurityBundle\Entity\Group as BaseGroup;
use Austral\SecurityBundle\Repository\GroupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Group of security-bundle, for the tests of the bridge with admin-bundle.
 */
#[ORM\Table(name: 'austral_security_group')]
#[ORM\Entity(repositoryClass: GroupRepository::class)]
class Group extends BaseGroup
{
}
