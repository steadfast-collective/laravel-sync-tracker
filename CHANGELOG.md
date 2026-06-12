# Changelog

All notable changes to `laravel-sync-tracker` will be documented in this file.

## Unreleased Changes

Added: 

- Added `setSyncMetadata` and `mergeSyncMetadata` trait methods
- Added findByExternalId method to models
- Added `allow_empty_source` config option — when enabled (the default) calls that omit the source resolve to the `'default'` source; disable it to make `syncData`, `markAsSynced`, `setSyncMetadata`, `mergeSyncMetadata` and `findByExternalId` throw an `EmptySourceException` when no source is passed.
- Added the source-scoped sync API: `$model->syncData($source)` returns the `SyncTrackedEntity` for that source, which now carries `markAsSynced`, `setSyncMetadata`, `mergeSyncMetadata` and `isSynced`. See [UPGRADE.md](UPGRADE.md) for the migration mapping.
- Added `SyncTrackedEntity::for($model, $source)`, the `withoutLifecycle()` scope, `isLifecycle()`, and the `SyncTrackedEntity::LIFECYCLE_SOURCE` constant
- Added an optional `$source` parameter to `SyncTracker::getSyncInfo()` and `SyncTracker::isSynced()`

Removed:

- Removed the old trait API (`markAsSynced`, `setSyncMetadata`, `mergeSyncMetadata`, `getExternalIdFromSource`, `getExternalId`, `getSyncSource`, `getSyncMetadata`, `isSynced` and the `syncTracking` relation). Deprecated versions can be poly-filled with the trait: `WizardingCode\FlowNetwork\SyncTracker\Traits\DeprecatedSyncTrackerMethods`.

Fixed:

- Fixed support for tracking a model against multiple sources independently
- Fixed sync tracking getters returning the never-synced lifecycle row instead of the most recently synced row on PostgreSQL (NULL `synced_at` sorts first on a descending order there)
- Fixed `mergeSyncMetadata` merging the most recently synced row's metadata into the given source instead of merging that source's own metadata
- Fixed `SyncTracker::markAsSynced` wiping a source's stored metadata and external ID when called without them
- Fixed morph map support: tracking rows are now written and queried via `getMorphClass()` instead of `get_class()`

Breaking:

- Dropped support for Laravel 10 and PHP 8.1. The package now requires PHP 8.2+ and Laravel 11+. The `doctrine/dbal` dependency is no longer needed (Laravel 11+ alters columns natively) and has been removed.
- Made all parameters of markSynced nullable to update SyncData timestamps without having to pass the data every time.
- An empty source string now always throws an `EmptySourceException`. Omitting the source stays allowed by default but now targets the `'default'` source instead of a `NULL`-source row (see `allow_empty_source` above).
- Model lifecycle events (created/updated/deleted) now record their timestamps on a dedicated lifecycle row under the `'_lifecycle'` sentinel source.
- The `EntitySynced` event now fires when syncing via the trait as well, not only via the facade.
- The `source` column is now `NOT NULL` and part of the unique index (one tracking row per model **per source**). Existing `NULL`-source rows must be deduplicated and backfilled manually **before** migrating — see [UPGRADE.md](UPGRADE.md). If you previously published the package migrations, re-publish them first so the new migration is copied into your app:

  ```bash
  php artisan vendor:publish --provider="WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider" --tag="migrations"
  php artisan migrate
  ```

## 1.0.0 - 2025-03-12

- Initial release
