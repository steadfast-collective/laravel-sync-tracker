# Changelog

All notable changes to `laravel-sync-tracker` will be documented in this file.

## Unreleased Changes

Added: 

- Added `setSyncMetadata` and `mergeSyncMetadata` trait methods
- Added findByExternalId method to models

Fixed:

- Fixed support for tracking a model against multiple sources independently
- Fixed sync tracking getters returning the never-synced lifecycle row instead of the most recently synced row on PostgreSQL (NULL `synced_at` sorts first on a descending order there)

Breaking:

- Made all parameters of markSynced nullable to update SyncData timestamps without having to pass the data every time.
- Model lifecycle events (created/updated/deleted) now record their timestamps on a sourceless lifecycle row only. If you relied on these stamps appearing on a source's tracking row (e.g. if any of your `source` fields are not null`) you will need to migrate them by creating a new SyncTrackedEntity entry without a source for each item and copying the timestamp fields.
- The unique index on the sync tracking table now includes `source` (one tracking row per model **per source**). If you previously published the package migrations, re-publish them first so the new migration is copied into your app:

  ```bash
  php artisan vendor:publish --provider="WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider" --tag="migrations"
  php artisan migrate
  ```

## 1.0.0 - 2025-03-12

- Initial release
