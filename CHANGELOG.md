# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-26

### Added

- Tab-grouped settings tool page for Laravel Nova 5.
- Settings defined in code with real Nova fields, synced into the database
  (`nova-settings:sync`, optional auto sync and `--prune`).
- Settings and setting groups defined from the Nova UI through two resources,
  with a choices table for Select, Multi select and Boolean group types.
- `setting()` and `setting_url()` helpers and the `NovaSettings` facade
  (`get`, `set`, `all`, `flushCache`).
- Value casting per field class (bool, int/float, array, Carbon) and forever cache.
- `SettingsSaved` event.

[Unreleased]: https://github.com/lucacermenati/nova-settings/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/lucacermenati/nova-settings/releases/tag/v1.0.0
