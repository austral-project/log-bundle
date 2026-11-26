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

namespace Austral\LogBundle\Tests\Context;

use Austral\LogBundle\Context\ContextCollector;
use Austral\LogBundle\Context\Sanitizer;
use Austral\LogBundle\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ContextCollectorTest extends TestCase
{
  public function testTheIdOfAnAustralUser(): void
  {
    $tokens = new TokenStorage();
    $collector = new ContextCollector(new Sanitizer(), new RequestStack(), $tokens);
    self::assertNull($collector->userId(), 'Nobody logged in.');

    $user = new User();
    $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
    self::assertSame($user->getId(), $collector->userId());

    $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('jane@austral.dev', null), 'main'));
    self::assertNull($collector->userId(), 'Only the id, never the email of another kind of user.');
  }

  public function testWithoutSecurityAndWithoutRequest(): void
  {
    $collector = new ContextCollector(new Sanitizer());

    self::assertNull($collector->userId());
    self::assertSame(['url' => null, 'method' => null, 'route' => null, 'headers' => []], $collector->request());
  }

  public function testAnUntrustedHostIsNotRead(): void
  {
    Request::setTrustedHosts(['^austral\.dev$']);
    try
    {
      $requests = new RequestStack();
      $requests->push(Request::create('https://evil.example/page'));

      self::assertSame('/page', new ContextCollector(new Sanitizer(), $requests)->request()['url']);
    }
    finally
    {
      Request::setTrustedHosts([]);
    }
  }
}
