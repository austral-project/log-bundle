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
- Configuration `austral_log`: `enabled`, `level`, `capture_client_errors`, `connection`, `occurrences_limit`, `entities`.
- Tests of the configuration, of the container, of the fingerprints, of the masking, of the factory, of the recorder and of the handler on SQLite.
