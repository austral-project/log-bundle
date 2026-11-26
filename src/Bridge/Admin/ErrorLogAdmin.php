<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Bridge\Admin;

use Austral\AdminBundle\Admin\Admin;
use Austral\AdminBundle\Event\FilterAdminEvent;
use Austral\AdminBundle\Event\ListAdminEvent;
use Austral\AdminBundle\Event\PageAdminEvent;
use Austral\AdminBundle\Event\QueryAdminEvent;
use Austral\AdminBundle\Routing\AdminUrlGenerator;
use Austral\FilterBundle\Filter\ChoiceFilter;
use Austral\FilterBundle\Filter\EnumFilter;
use Austral\FilterBundle\Filter\PeriodFilter;
use Austral\FilterBundle\Filter\SearchFilter;
use Austral\ListBundle\Action\Action;
use Austral\ListBundle\Column\ChoicesColumn;
use Austral\ListBundle\Column\DateColumn;
use Austral\ListBundle\Column\ValueColumn;
use Austral\LogBundle\Entity\Interfaces\ErrorLogInterface;
use Austral\LogBundle\Enum\ErrorStatus;
use Austral\LogBundle\Status\StatusManager;
use Monolog\Level;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Austral Error Log Admin.
 *
 * Module log/errors of admin-bundle: the list of the errors (filters on the status, the level and the date of the
 * last occurrence, search in the message, the class and the file), the page of an error with its last
 * occurrences, and the changes of status through the StatusManager (date of the change, LogEvents::STATUS_CHANGED).
 * The errors are written by the recorder only: no creation and no form.
 *
 * Actions of the module (extendActions):
 *   - show: GET, the page of the error;
 *   - status: POST + CSRF, the new status in "value"; back to the page of the error with "_back=show".
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ErrorLogAdmin extends Admin
{
  public const string ACTION_SHOW = 'show';

  public const string ACTION_STATUS = 'status';

  /**
   * The buttons of the changes of status: name => status.
   */
  public const array STATUS_ACTIONS = [
    'progress' => ErrorStatus::InProgress,
    'resolve' => ErrorStatus::Resolved,
    'ignore' => ErrorStatus::Ignored,
    'archive' => ErrorStatus::Archived,
    'reopen' => ErrorStatus::New,
  ];

  /**
   * The occurrences shown on the page of an error, the last ones.
   */
  public const int OCCURRENCES_SHOWN = 20;

  /**
   * ErrorLogAdmin constructor
   *
   * @param StatusManager     $statusManager
   * @param AdminUrlGenerator $urls
   */
  public function __construct(
    private readonly StatusManager $statusManager,
    private readonly AdminUrlGenerator $urls,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function configureQuery(QueryAdminEvent $event): void
  {
    if ($event->is(QueryAdminEvent::CONTEXT_LIST) || $event->is(QueryAdminEvent::CONTEXT_EXPORT))
    {
      $event->getQueryBuilder()->orderBy($event->getRootAlias().'.lastSeen', 'DESC');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function configureListMapper(ListAdminEvent $event): void
  {
    $module = $event->getModule();
    $urls = $this->urls;
    $mapper = $event->getMapper();
    $mapper
      ->add(new ChoicesColumn('status', [
        'label' => 'log.fields.status',
        'choices' => self::statusChoices(),
      ]))
      ->add(new ValueColumn('level', [
        'label' => 'log.fields.level',
        'getter' => static fn (ErrorLogInterface $error): string => self::levelName($error->getLevel()),
      ]))
      ->add(new ValueColumn('exceptionClass', ['label' => 'log.fields.exceptionClass']))
      ->add(new ValueColumn('message', [
        'label' => 'log.fields.message',
        'format' => static fn (mixed $message): string => mb_strimwidth(\is_string($message) ? $message : '', 0, 120, '…'),
      ]))
      ->add(new ValueColumn('occurrences', ['label' => 'log.fields.occurrences', 'align' => 'right']))
      ->add(new DateColumn('lastSeen', ['label' => 'log.fields.lastSeen']));

    if ($module->isGranted(self::ACTION_SHOW))
    {
      $mapper->setRowUrl(static fn (object $error): string => $urls->object($module, self::ACTION_SHOW, $error));
    }
    // One button by status instead of the default action "status".
    $mapper->removeRowAction(self::ACTION_STATUS);
    if ($module->isGranted(self::ACTION_STATUS))
    {
      $position = 10;
      foreach (self::STATUS_ACTIONS as $name => $status)
      {
        $mapper->addRowAction(new Action("log_{$name}", [
          'label' => "log.actions.{$name}",
          'url' => static fn (object $error): string => $urls->object($module, self::ACTION_STATUS, $error, ['value' => $status->value]),
          'attr' => ['data-method' => 'post'],
          'granted' => static fn (object|array|null $error = null): bool => $error instanceof ErrorLogInterface && $status !== $error->getStatus(),
        ]), $position++);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function configureFilterMapper(FilterAdminEvent $event): void
  {
    $levels = [];
    foreach ([Level::Error, Level::Critical, Level::Alert, Level::Emergency] as $level)
    {
      $levels[$level->getName()] = $level->value;
    }
    $event->getMapper()->filter()
      ->add(new SearchFilter('search', ['label' => 'log.fields.search', 'properties' => ['message', 'exceptionClass', 'file', 'channel']]))
      ->add(new EnumFilter('status', ['label' => 'log.fields.status', 'enum' => ErrorStatus::class, 'label_prefix' => 'log.status.']))
      ->add(new ChoiceFilter('level', ['label' => 'log.fields.level', 'choices' => $levels]))
      ->add(new PeriodFilter('lastSeen', ['label' => 'log.fields.lastSeen']));
  }

  /**
   * show
   * Page of an error: its details, its last occurrences and the buttons of the statuses.
   *
   * @param PageAdminEvent $event
   *
   * @return void
   */
  public function show(PageAdminEvent $event): void
  {
    $error = self::error($event);
    $module = $event->getModule();
    $statuses = [];
    if ($module->isGranted(self::ACTION_STATUS))
    {
      foreach (self::STATUS_ACTIONS as $name => $status)
      {
        if ($status !== $error->getStatus())
        {
          $statuses[$name] = [
            'label' => "log.actions.{$name}",
            'url' => $this->urls->object($module, self::ACTION_STATUS, $error),
            'value' => $status->value,
          ];
        }
      }
    }

    $event->setTemplate('@AustralLog/admin/error.html.twig')->addVars([
      'error' => $error,
      'level' => self::levelName($error->getLevel()),
      'occurrences' => $error->getOccurrenceList()->slice(0, self::OCCURRENCES_SHOWN),
      'statuses' => $statuses,
      'list_url' => $this->urls->module($module),
    ]);
  }

  /**
   * changeStatus
   * Changes the status of an error (POST, CSRF checked by the admin), then goes back to the list, or to the page
   * of the error with "_back=show".
   *
   * @param PageAdminEvent $event
   *
   * @return void
   */
  public function changeStatus(PageAdminEvent $event): void
  {
    $request = $event->getRequest();
    if (!$request->isMethod('POST'))
    {
      throw new MethodNotAllowedHttpException(['POST'], 'The status of an error is changed with a POST.');
    }
    $value = $request->request->get('value', $request->query->get('value'));
    $status = \is_string($value) ? ErrorStatus::tryFrom($value) : null;
    if (null === $status)
    {
      throw new BadRequestHttpException('Unknown status of an error.');
    }
    $error = self::error($event);
    $this->statusManager->change($error, $status);

    if (self::ACTION_SHOW === $request->request->get('_back', $request->query->get('_back')))
    {
      $event->setResponse(new RedirectResponse($this->urls->object($event->getModule(), self::ACTION_SHOW, $error)));
    }
  }

  /**
   * statusChoices
   * The statuses for the ChoicesColumn: value => translation key.
   *
   * @return array<string, string>
   */
  public static function statusChoices(): array
  {
    $choices = [];
    foreach (ErrorStatus::cases() as $status)
    {
      $choices[$status->value] = "log.status.{$status->value}";
    }

    return $choices;
  }

  /**
   * levelName
   *
   * @param int $level Monolog\Level value
   *
   * @return string
   */
  public static function levelName(int $level): string
  {
    return Level::tryFrom($level)?->getName() ?? (string) $level;
  }

  /**
   * error
   *
   * @param PageAdminEvent $event
   *
   * @return ErrorLogInterface
   */
  private static function error(PageAdminEvent $event): ErrorLogInterface
  {
    $error = $event->getObject();
    if (!$error instanceof ErrorLogInterface)
    {
      throw new \LogicException(\sprintf('The module "%s" is not a module of the errors.', $event->getModule()->getPath()));
    }

    return $error;
  }
}
