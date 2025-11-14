# Changelog

All notable changes to `laravel-sync-tracker` will be documented in this file.

## Unreleased Changes

Added: 

- Added `setSyncMetadata` and `mergeSyncMetadata` trait methods
- Added findByExternalId method to models

Breaking:

- Made all parameters of markSynced nullable to update SyncData timestamps without having to pass the data every time.

## 1.0.0 - 2025-03-12

- Initial release
