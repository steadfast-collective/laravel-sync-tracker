# Changelog

All notable changes to `laravel-sync-tracker` will be documented in this file.

## Unreleased Changes

Added: 

- Added `setSyncMetadata` and `mergeSyncMetadata` trait methods
- Added findByExternalId method to models

Breaking:

- Made the $metadata parameter of markSynced nullable to update SyncData without changing metadata

## 1.0.0 - 2025-03-12

- Initial release
