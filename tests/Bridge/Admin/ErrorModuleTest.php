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

namespace Austral\LogBundle\Tests\Bridge\Admin;

use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Tests\Fixtures\Entity\ErrorOccurrence;

/**
 * The module log/errors of the bridge, as root.
 */
final class ErrorModuleTest extends AdminWebTestCase
{
  private const string LIST = '/austral-admin/log/errors';

  public function testTheModuleIsInTheAdmin(): void
  {
    $this->login('root');
    $crawler = $this->client->request('GET', '/austral-admin/log');

    self::assertResponseIsSuccessful();
    self::assertSame([self::LIST], $crawler->filter('.austral-admin-tiles a')->each(static fn ($link): string => (string) $link->attr('href')));
  }

  public function testTheListOfTheErrors(): void
  {
    $resolved = $this->error(['fingerprint' => str_repeat('b', 64), 'message' => 'Payment refused', 'level' => 400, 'file' => 'src/Service/PaymentService.php'], ErrorStatus::Resolved);
    $this->error();
    $this->login('root');

    $crawler = $this->client->request('GET', self::LIST);
    self::assertResponseIsSuccessful();
    $list = $crawler->filter('tbody')->text();
    self::assertStringContainsString('Order 12 failed', $list);
    self::assertStringContainsString('CRITICAL', $list);
    self::assertStringContainsString('Payment refused', $list);
    self::assertStringContainsString('Resolved', $list);
    self::assertSame(0, $crawler->filter('a[href$="/new"]')->count(), 'No creation.');
    self::assertSame(0, $crawler->filter('a[href$="/edit"]')->count(), 'No form.');
    self::assertGreaterThan(0, $crawler->filter('form[action*="/'.$resolved->getId().'/status?value=new"]')->count(), 'A resolved error can be reopened from the list.');
    self::assertSame(0, $crawler->filter('form[action*="/'.$resolved->getId().'/status?value=resolved"]')->count(), 'Not resolved again.');

    $crawler = $this->client->request('GET', self::LIST.'?filters[default][status]=resolved');
    self::assertResponseIsSuccessful();
    $list = $crawler->filter('tbody')->text();
    self::assertStringContainsString('Payment refused', $list);
    self::assertStringNotContainsString('Order 12 failed', $list);

    $crawler = $this->client->request('GET', self::LIST.'?filters[default][search]=OrderService');
    self::assertStringNotContainsString('Payment refused', $crawler->filter('tbody')->text(), 'Search in the file.');
  }

  public function testThePageOfAnError(): void
  {
    $error = $this->error();
    $this->login('root');

    $crawler = $this->client->request('GET', self::LIST.'/'.$error->getId().'/show');
    self::assertResponseIsSuccessful();
    $page = $crawler->filter('.austral-log-error')->text();
    self::assertStringContainsString('RuntimeException', $page);
    self::assertStringContainsString('Order 12 failed', $page);
    self::assertStringContainsString('src/Service/OrderService.php:42', $page);
    self::assertStringContainsString('https://austral.dev/orders/12', $page);
    self::assertStringContainsString('order_pay', $page);
    self::assertStringContainsString('App\Service\OrderService->pay()', $page);
    self::assertStringContainsString('"order": 12', $crawler->filter('.austral-log-occurrence pre')->text());
    self::assertSame(['progress', 'resolve', 'ignore', 'archive'], $crawler->filter('.austral-log-error-statuses button')->each(static fn ($button): string => str_replace('austral-log-status-', '', (string) $button->attr('class'))), 'Every status but the current one.');
  }

  public function testTheStatusIsChangedWithTheStatusManager(): void
  {
    $error = $this->error();
    $this->login('root');
    $token = $this->csrfToken(self::LIST);

    $this->client->request('POST', self::LIST.'/'.$error->getId().'/status', ['_token' => $token, 'value' => 'resolved', '_back' => 'show']);

    self::assertResponseRedirects(self::LIST.'/'.$error->getId().'/show');
    $this->doctrine()->clear();
    $saved = $this->findError();
    self::assertSame(ErrorStatus::Resolved, $saved->getStatus());
    self::assertNotNull($saved->getStatusChangedAt(), 'Changed by the StatusManager, with its date.');

    $this->client->request('POST', self::LIST.'/'.$error->getId().'/status?value=archived', ['_token' => $token]);
    self::assertResponseRedirects();
    $this->doctrine()->clear();
    self::assertSame(ErrorStatus::Archived, $this->findError()->getStatus());
  }

  public function testTheStatusNeedsAPostAValidValueAndTheToken(): void
  {
    $error = $this->error();
    $this->login('root');
    $token = $this->csrfToken(self::LIST);
    $url = self::LIST.'/'.$error->getId().'/status';

    $this->client->request('GET', $url.'?value=resolved');
    self::assertResponseStatusCodeSame(405);
    $this->client->request('POST', $url, ['_token' => $token, 'value' => 'done']);
    self::assertResponseStatusCodeSame(400);
    $this->client->request('POST', $url, ['_token' => 'wrong', 'value' => 'resolved']);
    self::assertResponseStatusCodeSame(403);

    $this->doctrine()->clear();
    self::assertSame(ErrorStatus::New, $this->findError()->getStatus());
  }

  public function testTheDeleteRemovesTheOccurrences(): void
  {
    $error = $this->error();
    $this->login('root');
    $token = $this->csrfToken(self::LIST);

    $this->client->request('POST', self::LIST.'/'.$error->getId().'/delete', ['_token' => $token]);

    self::assertResponseRedirects();
    $this->doctrine()->clear();
    self::assertSame(0, $this->doctrine()->getRepository(ErrorOccurrence::class)->count([]));
  }

  public function testTheExport(): void
  {
    $this->error();
    $this->login('root');

    $this->client->request('GET', self::LIST.'/export.csv');

    self::assertResponseIsSuccessful();
    self::assertStringContainsString('Order 12 failed', (string) $this->client->getInternalResponse()->getContent());
  }

  public function testTheConfigurationChecks(): void
  {
    $this->error();
    $this->login('root');

    $crawler = $this->client->request('GET', '/austral-admin/status-configuration');

    self::assertResponseIsSuccessful();
    $page = $crawler->text();
    self::assertStringContainsString('Errors of the site', $page);
    self::assertStringContainsString('Recording of the errors', $page);
    self::assertStringContainsString('New errors', $page);
  }
}
