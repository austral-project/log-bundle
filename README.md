# Austral Log Bundle

[![Version](https://img.shields.io/packagist/v/austral/log-bundle?label=version)](https://packagist.org/packages/austral/log-bundle)
[![CI](https://github.com/austral-project/log-bundle/actions/workflows/ci.yml/badge.svg?branch=4.x)](https://github.com/austral-project/log-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/austral/log-bundle/php?version=4.x-dev)](https://packagist.org/packages/austral/log-bundle)
[![Symfony](https://img.shields.io/badge/symfony-7.4-black)](https://symfony.com/releases/7.4)
[![License](https://img.shields.io/github/license/austral-project/log-bundle)](LICENSE)

The errors of the site, recorded in the database and grouped: each error has its counter, its first and last date, its status and its last occurrences with their context. No external service.

> This bundle is part of [Austral](https://github.com/austral-project), a modular back office and CMS for Symfony.

## Requirements

- PHP 8.4 or higher
- Symfony 7.4, with MonologBundle
- Doctrine ORM 3

## Installation

```bash
composer require austral/log-bundle
```

If you do not use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Austral\LogBundle\AustralLogBundle::class => ['all' => true],
];
```

Create the two entities of the application from the skeletons `src/Skeleton/Entity/ErrorLog.php` and `src/Skeleton/Entity/ErrorOccurrence.php` (`App\Entity\Austral\LogBundle\`, tables `austral_log_error` and `austral_log_error_occurrence`), then update the database schema:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Nothing else: the bundle adds its handler to Monolog itself.

## Configuration

All the keys are optional:

```yaml
# config/packages/austral_log.yaml
austral_log:
  enabled: true                # false: no error is recorded
  level: error                 # lowest level recorded: error, critical, alert or emergency
  capture_client_errors: []    # 4xx codes recorded too, e.g. [404, 403]
  connection: default          # Doctrine connection of the tables
  occurrences_limit: 50        # occurrences kept by error, the last ones
  purge:
    after_days: 90             # austral:log:purge deletes the errors resolved or archived for 90 days; 0: never
    statuses: [ resolved, archived ]
  report:
    enabled: false             # austral:log:report: email of the new errors (symfony/mailer, twig/twig)
    frequency: daily           # hourly, daily or weekly
    recipients: [ dev@example.com ]
    from: no-reply@example.com
    subject: Errors of the site
    limit: 50                  # errors listed in the email, the others are counted
  entities:
    error: App\Entity\Austral\LogBundle\ErrorLog
    occurrence: App\Entity\Austral\LogBundle\ErrorOccurrence

when@test:
  austral_log:
    enabled: false
```

## Usage

### What is recorded

Every Monolog record of the configured level or higher, from every channel: uncaught exceptions of the HTTP requests, of the commands and of the Messenger workers, and the `$logger->error()` of the application. The record still goes to the other handlers (log files...).

The HTTP errors of the visitors (4xx: 404, 403, 405...) are not recorded by default: list the codes to record in `capture_client_errors`, e.g. `[404, 403]`. The 5xx are always recorded.

### Grouping

The occurrences of the same error share a fingerprint: the class of the exception, its file (from the root of the project, so a new deployment folder keeps the errors), its line and its message without the values (numbers, quoted values, UUIDs, emails...). `User 12 not found` and `User 13 not found` are the same error. Without exception, the error is the message of the record in its channel.

A change of line of the exception (the file was edited) gives a new error.

### Statuses

`new`, `in_progress`, `resolved`, `ignored`, `archived`. When an error comes back:

- a resolved or archived error goes back to `new`, with its regression date;
- the other statuses are kept: an ignored error stays ignored, its counter goes on.

The status is changed with `austral.log.status_manager`, for one error or several at once (the actions of the back office):

```php
$statusManager->change($error, ErrorStatus::Resolved);
$statusManager->changeAll($errors, ErrorStatus::Archived); // number of errors changed
```

The date of the change is saved; an error that already has the status is left as it is. After the save, `austral.log.status_changed` (`ErrorStatusChangedEvent`, with the previous status) is dispatched for each error that changed. A regression changes the status and its date too, without event: the errors are recorded in SQL, from the logger.

### Purge

```bash
php bin/console austral:log:purge                     # every day, by cron or Symfony Scheduler
php bin/console austral:log:purge --dry-run           # counts, deletes nothing
php bin/console austral:log:purge --days=30 --status=archived
```

Deletes the errors resolved or archived for more than `purge.after_days` days (since the change of their status), with their occurrences, then dispatches `austral.log.purged` (`ErrorsPurgedEvent`). The new, in progress and ignored errors are never purged: an ignored error must stay ignored, and would come back as new after a purge. `after_days: 0` disables the purge without `--days`.

### Email report

Never one email by error: a report of the new errors and of the regressions since the last report, at the chosen frequency, and nothing when there is nothing new.

```bash
composer require symfony/mailer twig/twig
```

```yaml
austral_log:
  report:
    enabled: true
    frequency: daily
    recipients: [ dev@example.com ]
    from: no-reply@example.com
```

```bash
php bin/console austral:log:report            # every hour, by cron or Symfony Scheduler, whatever the frequency
php bin/console austral:log:report --force    # now, whatever the frequency
php bin/console austral:log:report --dry-run  # lists the errors of the report, sends nothing
```

The command sends at most one report by period (with a margin of five minutes for the cron). A report lists the new errors never reported, and the errors that came back after the last report that listed them; the ignored, in progress, resolved and archived errors are not reported. The errors are marked as reported only once the email is sent: a failed sending is retried at the next run.

The email has an HTML and a text version. Override them in `templates/bundles/AustralLogBundle/email/report.html.twig` and `report.txt.twig` (variables: `report`, an `ErrorReport`, and `subject`).

### Context of an occurrence

The URL, the method and the route of the request, the id of the logged in user (when it is an Austral entity), the environment, the request headers, the context and the extra of the record, the PHP and Symfony versions, and the trace of the exception and of its previous exceptions.

Sensitive values are masked (`***`): the keys whose name contains `password`, `secret`, `token`, `authorization`, `cookie`, `session`, `csrf`, `api_key`, `credential`... in the context, the extra, the headers and the query of the URL. The body of the request and the arguments of the calls in the trace are never recorded. The messages are recorded as they are: do not put secrets in the messages of your exceptions.

### Reliability

The errors are written with plain SQL on a second connection to the database, built like the connection `austral_log.connection` and opened only when an error is recorded: an error is saved even when the transaction of the application is rolled back.

Recording never breaks the application. When the database does not answer, nothing is saved (the log files keep the error) and the bundle stops trying until the end of the request (or the next message of a worker), so a broken database does not slow down each error. `austral.log.recorder` gives the reason with `getLastFailure()`.

The names of the columns are fixed (the bundle writes them): an application can add columns to its entities, not rename these.

## Administration

With [austral/admin-bundle](https://github.com/austral-project/admin-bundle), the module **Log › Errors** (`log/errors`) is added to the back office (`src/Bridge/Admin/`), nothing to configure:

- the list of the errors, the last seen first: status, level, error, message, occurrences, last seen; filters on the status, the level and the date of the last occurrence, search in the message, the class, the file and the channel; export CSV and JSON;
- the page of an error (action `show`): its details, its last 20 occurrences with their request, their trace and their context;
- the changes of status, from the list and from the page of an error (action `status`, POST with the CSRF token of the admin), through `austral.log.status_manager`: the date of the change is saved and `austral.log.status_changed` is dispatched;
- delete an error (with its occurrences) or all the errors;
- the checks of the configuration page: recording enabled, tables readable, number of new errors.

The errors are written by the bundle only: no creation and no form.

| Action | Role |
|---|---|
| list | `ROLE_LOG_ERRORS` (and `ROLE_LOG` for the container) |
| page of an error | `ROLE_LOG_ERRORS_SHOW` |
| change the status | `ROLE_LOG_ERRORS_STATUS` |
| delete, delete all | `ROLE_LOG_ERRORS_DELETE`, `ROLE_LOG_ERRORS_TRUNCATE` |
| export | `ROLE_LOG_ERRORS_DOWNLOAD` |

`php bin/console austral:admin:roles` creates these roles. The page of an error is the template `@AustralLog/admin/error.html.twig`, to override in `templates/bundles/AustralLogBundle/admin/error.html.twig`. The module is changed or disabled in the configuration of admin-bundle:

```yaml
austral_admin:
  modules:
    log:
      children:
        errors: { enabled: false }
```

## Documentation

The full documentation is available at https://austral.dev.

## Versions

| Bundle | Symfony | PHP | Branch |
|--------|---------|-----|---------|
| 4.x    | 7.4     | ≥ 8.4 | `4.x` |

New bundle in Austral 4.0, there is no 3.x version. See the [CHANGELOG](CHANGELOG.md) and [UPGRADE-4.0](UPGRADE-4.0.md).

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/austral-project/.github/blob/master/CONTRIBUTING.md). To report a security issue, follow the [security policy](https://github.com/austral-project/.github/blob/master/SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).

## Credits

Created by [Matthieu Beurel](https://www.mbeurel.com). Sponsored by [Yipikai Studio](https://yipikai.studio).
