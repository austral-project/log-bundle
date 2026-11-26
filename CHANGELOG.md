# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

First version of `austral/log-bundle`: the errors of the site, recorded in the database and grouped.

### Added

- `ErrorLog` and `ErrorOccurrence` entities (mapped superclasses), their interfaces, their repositories and the `App\Entity\Austral\LogBundle\` skeletons (tables `austral_log_error` and `austral_log_error_occurrence`).
- `ErrorStatus` enum: `new`, `in_progress`, `resolved`, `ignored`, `archived`; a resolved or archived error that comes back goes back to `new`.
- `Monolog\ErrorLogHandler`, added to MonologBundle by the extension (`prepend()`): records from the level `austral_log.level`, from every channel; the 4xx are ignored, except the codes listed in `austral_log.capture_client_errors`.
- `Fingerprint\FingerprintGenerator`: the occurrences are grouped by class, file from the root of the project, line and message without its values.
- `Context\Sanitizer` and `Context\ContextCollector`: context of each occurrence (URL, method, route, user id, headers, environment, versions, trace without the arguments), sensitive values masked.
- `Record\ErrorRecordFactory` and `Record\ErrorRecorder` (`austral.log.recorder`): writes with plain SQL on a second connection (`austral.log.connection`, built by `LogConnectionPass` like the connection `austral_log.connection`), out of the transaction of the application; keeps the last `austral_log.occurrences_limit` occurrences; never throws, and stops trying after a failure until reset.
- `Status\StatusManager` (`austral.log.status_manager`): changes the status of one or several errors, saves the date of the change and dispatches `austral.log.status_changed` (`ErrorStatusChangedEvent`); `ErrorLog::changeStatus()`.
- A regression (resolved or archived error that comes back) also sets the date of the change of status.
- `EntityManager\ErrorLogEntityManager` (`austral.entity_manager.error_log`).
- `austral:log:purge` command and `Purge\ErrorPurger` (`austral.log.purger`): deletes the errors resolved or archived for more than `austral_log.purge.after_days` days, with their occurrences, by batches; `--days`, `--status`, `--dry-run`; dispatches `austral.log.purged` (`ErrorsPurgedEvent`). The new, in progress and ignored errors are never purged.
- `austral:log:report` command and `Report\ErrorReporter` (`austral.log.reporter`), defined when `austral_log.report.enabled` is true (needs symfony/mailer and twig/twig): email report of the new errors and of the regressions since the last report, at most one by period (`hourly`, `daily`, `weekly`), nothing when there is nothing new; `--force`, `--dry-run`. HTML and text templates `@AustralLog/email/report.html.twig` and `report.txt.twig`. The errors are marked as reported (`ErrorLog::$notifiedAt`, column `notified_at`) once the email is sent.
- Bridge with admin-bundle (`src/Bridge/Admin/`, loaded only when admin-bundle is enabled): module `log/errors` with the list of the errors (filters, search, export), the page of an error with its last occurrences (action `show`, template `@AustralLog/admin/error.html.twig`), the changes of status through the `StatusManager` (action `status`, POST + CSRF), delete and delete all; checks of the configuration page (`ConfigurationCheckListener`); translations `austral.en.yaml` / `austral.fr.yaml`.
- The occurrences of an error are deleted with it by the ORM too (`cascade: ['remove']`), on the databases without cascade of the foreign keys.
- Configuration `austral_log`: `enabled`, `level`, `capture_client_errors`, `connection`, `occurrences_limit`, `purge` (`after_days`, `statuses`), `report` (`enabled`, `frequency`, `recipients`, `from`, `subject`, `limit`), `entities`.
- Tests of the configuration, of the container, of the fingerprints, of the masking, of the factory, of the recorder, of the handler, of the statuses, of the purge, of the report and of the module of the admin (functional tests) on SQLite.
