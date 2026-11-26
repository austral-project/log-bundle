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

/**
 * The module log/errors as an editor who sees the errors without changing their status.
 */
final class ErrorModuleRightsTest extends AdminWebTestCase
{
  public function testTheEditorSeesTheErrorsWithoutTheStatuses(): void
  {
    $error = $this->error();
    $this->login('editor');

    $crawler = $this->client->request('GET', '/austral-admin/log/errors');
    self::assertResponseIsSuccessful();
    self::assertSame(0, $crawler->filter('form[action*="/status"]')->count(), 'No status button without ROLE_LOG_ERRORS_STATUS.');

    $crawler = $this->client->request('GET', '/austral-admin/log/errors/'.$error->getId().'/show');
    self::assertResponseIsSuccessful();
    self::assertSame(0, $crawler->filter('.austral-log-error-statuses')->count());

    $this->client->request('POST', '/austral-admin/log/errors/'.$error->getId().'/status', ['_token' => $this->csrfToken('/austral-admin/log/errors'), 'value' => 'resolved']);
    self::assertResponseStatusCodeSame(403);
    $this->doctrine()->clear();
    self::assertSame(ErrorStatus::New, $this->findError()->getStatus());
  }
}
