<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Bridge\Admin\Listener;

use Austral\AdminBundle\Configuration\ConfigurationCheck;
use Austral\AdminBundle\Event\ConfigurationCheckEvent;
use Austral\LogBundle\EntityManager\ErrorLogEntityManager;
use Austral\LogBundle\Enum\ErrorStatus;

/**
 * Austral Configuration Check Listener (austral.admin.configuration_check).
 *
 * The checks of log-bundle on the configuration page of the admin: the errors are recorded, their tables can be
 * read, and the number of new errors.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class ConfigurationCheckListener
{
  public const string CATEGORY = 'log';

  /**
   * ConfigurationCheckListener constructor
   *
   * @param ErrorLogEntityManager $errors
   * @param bool                  $recordingEnabled austral_log.enabled
   */
  public function __construct(
    private readonly ErrorLogEntityManager $errors,
    private readonly bool $recordingEnabled,
  ) {
  }

  /**
   * __invoke
   *
   * @param ConfigurationCheckEvent $event
   *
   * @return void
   */
  public function __invoke(ConfigurationCheckEvent $event): void
  {
    $event->add(ConfigurationCheck::check(self::CATEGORY, 'log_enabled', $this->recordingEnabled, 'log.configuration.enabled.entitled', 'log.configuration.enabled.description'));
    try
    {
      $new = $this->errors->getRepository()?->count(['status' => ErrorStatus::New]);
    }
    catch (\Throwable)
    {
      $event->add(new ConfigurationCheck(self::CATEGORY, 'log_tables', ConfigurationCheck::STATUS_ERROR, 'log.configuration.tables.entitled', 'log.configuration.tables.description'));

      return;
    }
    $event->add(new ConfigurationCheck(self::CATEGORY, 'log_new', 0 === $new ? ConfigurationCheck::STATUS_SUCCESS : ConfigurationCheck::STATUS_WARNING, 'log.configuration.new.entitled', 'log.configuration.new.description', (string) $new));
  }
}
