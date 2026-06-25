# Laravel Sync Tracker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andreagroferreira/laravel-sync-tracker.svg?style=flat-square)](https://packagist.org/packages/andreagroferreira/laravel-sync-tracker)
[![Total Downloads](https://img.shields.io/packagist/dt/andreagroferreira/laravel-sync-tracker.svg?style=flat-square)](https://packagist.org/packages/andreagroferreira/laravel-sync-tracker)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![PHP Version Support](https://img.shields.io/packagist/php-v/andreagroferreira/laravel-sync-tracker.svg?style=flat-square)](https://packagist.org/packages/andreagroferreira/laravel-sync-tracker)
[![Laravel Version Support](https://img.shields.io/badge/Laravel-11.x%20|%2012.x%20|%2013.x-brightgreen.svg?style=flat-square)](https://packagist.org/packages/andreagroferreira/laravel-sync-tracker)
[![GitHub stars](https://img.shields.io/github/stars/steadfast-collective/laravel-sync-tracker.svg?style=social&label=Star&maxAge=2592000)](https://github.com/steadfast-collective/laravel-sync-tracker)

Track the sync status of any Eloquent model against external systems — CRMs, ERPs, e-commerce platforms, or any third-party API. Record external IDs, per-source metadata, and sync timestamps, then query your models by what they're synced to.

```php
$user->syncData('salesforce')->markAsSynced('SF-123456', ['account_type' => 'customer']);

$user->syncData('salesforce')->isSynced();      // true
$user->syncData('salesforce')->external_id;      // 'SF-123456'

User::hasExternalId('salesforce')->get();        // every user synced to Salesforce
```

## About this fork

This fork builds on the original [`andreagroferreira/laravel-sync-tracker`](https://github.com/andreagroferreira/laravel-sync-tracker) by [@andreagroferreira](https://github.com/andreagroferreira), with breaking changes to properly support multiple sync sources and a simpler, source-scoped API.

- **The API is source-scoped.** `$model->syncData($source)` returns the tracking entity for one source, and every read and write happens on it. You no longer thread a `$source` argument through each call.
- **Sync metadata is separate from sync state.** You can `markAsSynced()` without touching metadata, and `mergeSyncMetadata()` to update a few keys instead of overwriting everything.
- **The old model methods were removed**, not just deprecated. They can be polyfilled by adding the `DeprecatedSyncTrackerMethods` trait — see [Upgrading](#upgrading-from-v1).
- The active examples below cover trait usage, which is how we use it. PRs with tests for the facade are welcome.

> **Upgrading from v1?** v2 is a breaking change (the `source` column becomes `NOT NULL` and existing data needs manual cleanup). Read [UPGRADE.md](UPGRADE.md) **before** migrating — it includes the full API mapping, the data-migration SQL, and a paste-ready AI migration prompt.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Features

- 🔄 **Track sync status** of any Eloquent model against external systems
- 🧩 **Multiple sources per model** — track the same record in Salesforce, HubSpot, and your ERP independently
- 🔍 **Find models by external ID** and source
- 📊 **Per-source metadata** for audit trails, with set-or-merge semantics
- 🕒 **Automatic lifecycle timestamps** for create / update / delete events
- 🔌 **Query scopes** to filter models by what they're synced to
- 🛠️ **Configurable** per model and globally

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [Real-world Examples](#real-world-examples)
- [Events](#events)
- [Custom Implementations](#custom-implementations)
- [Upgrading from v1](#upgrading-from-v1)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Installation

Install via Composer:

```bash
composer require andreagroferreira/laravel-sync-tracker
```

### Install with an AI agent

This fork isn't on Packagist, so it installs from the GitHub repo. Paste this into your coding agent:

```text
Add the steadfast-collective fork of andreagroferreira/laravel-sync-tracker to this Laravel app. It's hosted on a GitHub repo, not on Packagist. Add a VCS repository entry for https://github.com/steadfast-collective/laravel-sync-tracker to composer.json, then require it with `composer require andreagroferreira/laravel-sync-tracker:dev-fix/multi-source`. Publish the migrations (tag "migrations" on the WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider provider) and run `php artisan migrate`. Publishing the config (tag "config") is optional — only do it if the defaults need changing.
```

## Configuration

Publish the migrations and run them:

```bash
php artisan vendor:publish --provider="WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider" --tag="migrations"
php artisan migrate
```

Publishing the config is **optional** — the package works on its defaults. Only publish it if you need to change them:

```bash
php artisan vendor:publish --provider="WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider" --tag="config"
```

The published config file (`config/sync-tracker.php`):

```php
return [
    // The table name used to store sync tracking information.
    'table_name' => 'sync_tracked_entities',

    // Whether a tracking call may omit the sync source. When enabled (the default),
    // omitting the source resolves to the 'default' source. Disable it for strict
    // mode when working with multiple sources, so a missing source throws instead of
    // silently using 'default'. An empty source string ('') always throws.
    'allow_empty_source' => true,

    // Default automatic lifecycle tracking.
    'default_tracking' => [
        'track_created' => true,
        'track_updated' => true,
        'track_deleted' => true,
    ],

    // Per-model overrides for the lifecycle tracking above.
    'models' => [
        // App\Models\User::class => [
        //     'track_created' => true,
        //     'track_updated' => false,
        //     'track_deleted' => true,
        // ],
    ],
];
```

## Basic Usage

### Using the Trait

Add the `HasSyncTracking` trait to your model:

```php
use WizardingCode\FlowNetwork\SyncTracker\Traits\HasSyncTracking;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasSyncTracking;
}
```

Call `syncData()` to get the tracking entity, then read and write everything through it:

```php
$user = User::find(1);

// Mark the model as synced (external ID + optional metadata)
$user->syncData()->markAsSynced('external-123', ['meta' => 'data']);

// Check whether it's synced
if ($user->syncData()->isSynced()) {
    // ...
}

// Read sync information — the entity is a plain Eloquent model
$externalId = $user->syncData()->external_id;
$metadata   = $user->syncData()->metadata;
$syncedAt   = $user->syncData()->synced_at;

// Find a model by its external ID
$user = User::findByExternalId('external-123');
```

> Calls that omit the source use the `'default'` source. Tracking a model in more than one system? See [Multiple Source Systems](#multiple-source-systems).

### Using the Facade

Handy when you don't have the model's trait in hand:

```php
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;

// Mark a model as synced: (model, externalId, source, metadata)
SyncTracker::markAsSynced($model, 'external-123', metadata: ['meta' => 'data']);

// Check if synced
if (SyncTracker::isSynced($model)) {
    // ...
}

// Get the tracking row
$syncInfo = SyncTracker::getSyncInfo($model);
```

### Working with Metadata

Store arbitrary metadata with `setSyncMetadata` (replace) and `mergeSyncMetadata` (patch):

```php
$user = User::find(1);

// Replace all metadata
$user->syncData()->setSyncMetadata([
    'remote_modified_at' => $responseData['modified_at'],
    'direction'          => 'pull',
    'status'             => 'success',
]);

// Patch a few keys — remote_modified_at is left untouched
$user->syncData()->mergeSyncMetadata([
    'direction' => 'push',
    'status'    => 'failed',
]);
```

## Advanced Usage

### Multiple Source Systems

The examples so far used the `'default'` source. Pass a source name to `syncData()` to track the same model in several systems independently — each source keeps its own external ID, metadata, and timestamps:

```php
$user = User::find(1);

// Synced with Salesforce
$user->syncData('salesforce')->markAsSynced('SF-123456', [
    'last_sync'    => now(),
    'account_type' => 'customer',
]);

// ...and elsewhere, synced with HubSpot
$user->syncData('hubspot')->markAsSynced('HS-789012', [
    'contact_owner' => 'jane.doe@example.com',
    'lead_score'    => 85,
]);

// All real sync rows for this user (excluding the automatic lifecycle row)
$syncTrackers = $user->syncTrackers()->withoutLifecycle()->get();

// Each source's external ID
$salesforceId = $user->syncData('salesforce')->external_id;
$hubspotId    = $user->syncData('hubspot')->external_id;
```

> **Lifecycle rows:** create/update/delete timestamps are recorded on a separate row under the `'_lifecycle'` sentinel source, so they never clobber a real source's data. Filter it out of listings with the `withoutLifecycle()` scope.

### Query Scopes for Sync Status

The trait ships with the `hasExternalId` scope. It filters to models that have a tracking row carrying a non-null `external_id` (which also excludes the automatic lifecycle rows). Pass a source to require a sync to that specific one:

```php
// Synced to any source
Product::hasExternalId()->get();

// Synced specifically to 'shopify'
Product::hasExternalId('shopify')->get();
```

For finer-grained filtering, query the `syncTrackers()` relation directly (e.g. `whereHas('syncTrackers', ...)`) — its rows carry `source`, `synced_at`, and `metadata` columns.

## Real-world Examples

### E-commerce Platform Integration

```php
class ProductSyncService
{
    public function syncToShopify(Product $product)
    {
        $shopifyId = $product->syncData('shopify')->external_id;

        if ($shopifyId) {
            $response = $this->shopifyClient->updateProduct($shopifyId, [
                'title'              => $product->name,
                'price'              => $product->price,
                'inventory_quantity' => $product->stock,
            ]);
        } else {
            $response = $this->shopifyClient->createProduct([
                'title'              => $product->name,
                'price'              => $product->price,
                'inventory_quantity' => $product->stock,
            ]);

            $shopifyId = $response['id'];
        }

        $product->syncData('shopify')->markAsSynced($shopifyId, [
            'shopify_handle'     => $response['handle'],
            'variants_synced'    => count($response['variants']),
            'images_synced'      => count($response['images']),
            'shopify_updated_at' => $response['updated_at'],
            'inventory_tracked'  => true,
        ]);

        return $response;
    }
}
```

### Detecting Conflicts with `synced_at`

Use the stored `synced_at` to tell whether the remote record changed since your last sync, and stamp the outcome into metadata:

```php
class ContactSyncService
{
    public function syncWithCrm(User $user)
    {
        $tracker    = $user->syncData('crm');
        $externalId = $tracker->external_id;
        $fields     = $user->only(['name', 'email', 'phone']);

        $remoteChangedSinceLastSync = false;

        if ($externalId) {
            $crmData = $this->crmClient->getContact($externalId);
            $remoteChangedSinceLastSync = Carbon::parse($crmData['updated_at'])->gt($tracker->synced_at);

            $result = $this->crmClient->updateContact($externalId, $fields);
        } else {
            $result = $this->crmClient->createContact($fields);
        }

        $user->syncData('crm')->markAsSynced($result['id'], [
            'crm_updated_at'    => $result['updated_at'],
            'conflict_detected' => $remoteChangedSinceLastSync,
        ]);

        return $result;
    }
}
```

### Handling Failed Syncs

On failure, record the error in metadata without touching `synced_at` (so the row still reads as "last synced at X"):

```php
try {
    $response = $this->apiClient->createOrUpdate($product);

    $product->syncData('erp')->markAsSynced($response['id'], [
        'sync_status'           => 'success',
        'last_successful_sync'  => now(),
    ]);
} catch (ApiException $e) {
    $tracker = $product->syncData('erp');
    $retryCount = ($tracker->metadata['retry_count'] ?? 0) + 1;

    // mergeSyncMetadata patches keys without overwriting external_id or synced_at
    $tracker->mergeSyncMetadata([
        'sync_status'   => 'failed',
        'error_message' => $e->getMessage(),
        'error_code'    => $e->getCode(),
        'retry_count'   => $retryCount,
        'last_attempt'  => now()->toIso8601String(),
    ]);

    if ($retryCount < 5) {
        SyncRetryJob::dispatch($product)->delay(now()->addMinutes(30));
    }
}
```

### Tracking Bi-directional Syncs

```php
// When a local change is made, flag it for upstream sync
$product = Product::find(1);
$product->update(['price' => 29.99]);

$product->syncData('erp')->mergeSyncMetadata([
    'needs_upstream_sync' => true,
    'local_changes'       => ['price' => 29.99],
]);

// When receiving webhooks from an external system
public function handleExternalUpdate(Request $request)
{
    $externalId = $request->input('id');
    $source     = 'erp';

    $product = SyncTracker::findByExternalId($externalId, $source, Product::class);

    if ($product) {
        $product->update([
            'name' => $request->input('name'),
            'sku'  => $request->input('sku'),
        ]);

        $product->syncData($source)->markAsSynced($externalId, [
            'sync_type'         => 'downstream',
            'webhook_id'        => $request->input('webhook_id'),
            'external_updated_at' => $request->input('updated_at'),
        ]);
    }
}
```

## Events

`EntitySynced` is dispatched automatically on every successful sync — whether you sync via the trait (`syncData(...)->markAsSynced(...)`) or the facade. `SyncFailed` is provided for you to dispatch from your own failure handling.

```php
// In your EventServiceProvider
protected $listen = [
    \WizardingCode\FlowNetwork\SyncTracker\Events\EntitySynced::class => [
        \App\Listeners\HandleEntitySynced::class,
    ],
    \WizardingCode\FlowNetwork\SyncTracker\Events\SyncFailed::class => [
        \App\Listeners\HandleSyncFailed::class,
    ],
];
```

`EntitySynced` carries `$model` and `$syncInfo` (the `SyncTrackedEntity`):

```php
namespace App\Listeners;

use WizardingCode\FlowNetwork\SyncTracker\Events\EntitySynced;

class HandleEntitySynced
{
    public function handle(EntitySynced $event)
    {
        $model    = $event->model;
        $syncInfo = $event->syncInfo;

        if ($model instanceof \App\Models\CriticalEntity) {
            \Notification::route('slack', config('services.slack.webhook_url'))
                ->notify(new \App\Notifications\EntitySynced($model, $syncInfo));
        }

        \Cache::tags([$model->getTable()])->flush();
    }
}
```

`SyncFailed` carries `$model`, `$source`, `$exception`, and `$metadata`.

## Custom Implementations

### A REST Endpoint for External Systems to Check Sync Status

```php
public function getSyncStatus(Request $request)
{
    $request->validate([
        'model_type' => 'required|string',
        'model_id'   => 'required',
        'source'     => 'required|string',
    ]);

    $modelClass = $this->getModelClassFromType($request->model_type);
    $model = $modelClass::find($request->model_id);

    if (! $model) {
        return response()->json(['error' => 'Model not found'], 404);
    }

    // syncTrackers() is the live relation; ->first() returns null when not synced
    $syncInfo = $model->syncTrackers()->where('source', $request->source)->first();

    if (! $syncInfo) {
        return response()->json([
            'sync_status' => 'not_synced',
            'model_type'  => $request->model_type,
            'model_id'    => $request->model_id,
            'source'      => $request->source,
        ]);
    }

    return response()->json([
        'sync_status'    => $syncInfo->synced_at ? 'synced' : 'pending',
        'external_id'    => $syncInfo->external_id,
        'last_synced_at' => $syncInfo->synced_at,
        'last_updated_at' => $syncInfo->updated_at,
        'metadata'       => $syncInfo->metadata,
    ]);
}
```

## Upgrading from v1

v2 moved the API onto the `SyncTrackedEntity` itself and made `source` a required, indexed column. **Existing `NULL`-source rows must be deduplicated and backfilled by hand before you migrate** — the package does not do it for you.

Read [UPGRADE.md](UPGRADE.md) for:

- the full deprecated → replacement method mapping,
- the data-cleanup SQL to run before `php artisan migrate`,
- a paste-ready prompt for migrating an app with an AI coding agent.

If you want the old model methods (`markAsSynced()`, `getExternalId()`, `getSyncMetadata()`, the `syncTracking` relation, etc.) to keep working as deprecated delegates while you migrate, add the add-on trait alongside `HasSyncTracking`:

```php
use WizardingCode\FlowNetwork\SyncTracker\Traits\HasSyncTracking;
use WizardingCode\FlowNetwork\SyncTracker\Traits\DeprecatedSyncTrackerMethods;

class User extends Model
{
    use HasSyncTracking;
    use DeprecatedSyncTrackerMethods; // optional, for backwards compatibility
}
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what's changed recently.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security issue in this fork, please email sami@steadfastcollective.com rather than using the issue tracker.

## Credits

- [André Ferreira](https://github.com/andreagroferreira) — original author
- [Steadfast Collective](https://github.com/steadfast-collective) — fork maintainer
- [Sami Walbury](https://github.com/patabugen) — v2 source-scoped multi-source API, per-source metadata, Laravel 11–13 / PHP 8.5 support, and Postgres/MySQL/morph-map fixes
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See the [License File](LICENSE.md) for details.
