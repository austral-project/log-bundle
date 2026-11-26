<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Context;

use Austral\Contracts\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Austral Context Collector.
 *
 * The request being handled and the logged in user, sensitive values masked. In the console and in the workers
 * there is no request: everything is null.
 *
 * The body of the request is never collected (passwords of the forms...).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ContextCollector
{
  /**
   * ContextCollector constructor
   *
   * @param Sanitizer                  $sanitizer
   * @param RequestStack|null          $requestStack
   * @param TokenStorageInterface|null $tokenStorage symfony/security-core is optional
   */
  public function __construct(
    private readonly Sanitizer $sanitizer,
    private readonly ?RequestStack $requestStack = null,
    private readonly ?TokenStorageInterface $tokenStorage = null,
  ) {
  }

  /**
   * request
   *
   * @return array{url: ?string, method: ?string, route: ?string, headers: array<array-key, mixed>}
   */
  public function request(): array
  {
    $context = ['url' => null, 'method' => null, 'route' => null, 'headers' => []];
    $request = $this->requestStack?->getCurrentRequest();
    if (!$request instanceof Request)
    {
      return $context;
    }
    $route = $request->attributes->get('_route');

    return [
      'url' => $this->url($request),
      'method' => $this->sanitizer->truncate($request->getMethod()),
      'route' => \is_string($route) ? $route : null,
      'headers' => $this->sanitizer->sanitize($request->headers->all()),
    ];
  }

  /**
   * userId
   * Id of the logged in user, when it is an Austral entity.
   *
   * @return string|null
   */
  public function userId(): ?string
  {
    $user = $this->tokenStorage?->getToken()?->getUser();

    return $user instanceof EntityInterface ? $user->getId() : null;
  }

  /**
   * url
   * The URL with the sensitive parameters of the query masked. An untrusted host (trusted_hosts) is not read.
   *
   * @param Request $request
   *
   * @return string
   */
  private function url(Request $request): string
  {
    try
    {
      $url = $request->getSchemeAndHttpHost();
    }
    catch (\Throwable)
    {
      $url = '';
    }
    $url .= $request->getBaseUrl().$request->getPathInfo();
    $query = $this->sanitizer->sanitize($request->query->all());
    if ([] !== $query)
    {
      $url .= '?'.http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    return $this->sanitizer->truncate($url);
  }
}
