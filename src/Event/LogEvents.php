<?php

/*
 * This file is part of the Austral Log Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\LogBundle\Event;

/**
 * Austral Log Events.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class LogEvents
{
  /**
   * The status of an error was changed by the StatusManager (ErrorStatusChangedEvent). Not dispatched for a
   * regression: the recorder writes in SQL, from the logger.
   */
  public const string STATUS_CHANGED = 'austral.log.status_changed';

  /**
   * Errors were purged by the ErrorPurger (ErrorsPurgedEvent).
   */
  public const string PURGED = 'austral.log.purged';
}
