# Upgrade Guide v1 to v2

v2 introduces big, breaking changes in order to fix support for multiple-sources, and simplify the API.

## To the source-scoped `syncData()` API

The sync tracking API moved onto the `SyncTrackedEntity` model itself. `$model->syncData($source)` resolves the tracking entity for one source; every read and write on that entity is already source-scoped, so nothing downstream needs to think about sources:

```php
$model->syncData('shopify')->markAsSynced('ext-1', ['k' => 'v']);
$model->syncData('shopify')->external_id;
$model->syncData('shopify')->metadata;
$model->syncData('shopify')->isSynced();
$model->syncData('shopify')->mergeSyncMetadata(['pushed_at' => now()]);
```

The old methods still work as `@deprecated` delegates (see the mapping table below).

### Breaking changes

1. **Empty source strings always throw `EmptySourceException`.** Omitting the source entirely stays allowed by default and now resolves to the `'default'` source (`SyncTrackedEntity::DEFAULT_SOURCE`) instead of a `NULL`-source row. Disable `allow_empty_source` to make omitting the source throw as well.
2. **Lifecycle rows use the `'_lifecycle'` sentinel source** (was `NULL`). The automatic created/updated/deleted stamps live on that row. Reach it explicitly with `$model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE)`; filter it out of `syncTrackers()` queries with the `withoutLifecycle()` scope.
3. **`EntitySynced` now fires from the trait path too.** Previously only the `SyncTracker` facade dispatched it. If you have listeners, expect events from `$model->syncData(...)->markAsSynced(...)` and the deprecated `$model->markAsSynced(...)` as well.
4. **Facade `markAsSynced` no longer wipes data on re-sync.** The `$metadata` parameter default changed from `[]` to `null`; omitting it (or the external ID) now leaves the stored values untouched instead of overwriting them.
5. **`trackable_type` is written via `getMorphClass()`** instead of `get_class()`. No change unless you use morph maps (which did not work with this package before).
6. The "Please save your syncTracking model before..." guards are gone — `syncData()` re-queries on each call, so there is no relation to go stale before a write.

### Database

The `source` column joins the unique index and becomes `NOT NULL`. The package does **not** migrate your data automatically — clean up existing `NULL`-source rows by hand **before** running the migrations, or the column change will fail.

**1. Deduplicate `NULL`-source rows.** The old unique index never protected them (SQL treats NULLs as distinct), so a model may have accumulated several. Keep the most recently synced row per model, e.g. via `php artisan tinker`:

```php
use Illuminate\Support\Facades\DB;

$table = config('sync-tracker.table_name', 'sync_tracked_entities');

DB::table($table)
    ->selectRaw('trackable_type, trackable_id')
    ->whereNull('source')
    ->groupBy('trackable_type', 'trackable_id')
    ->havingRaw('count(*) > 1')
    ->get()
    ->each(function ($duplicate) use ($table) {
        $keepId = DB::table($table)
            ->where('trackable_type', $duplicate->trackable_type)
            ->where('trackable_id', $duplicate->trackable_id)
            ->whereNull('source')
            ->orderByRaw('case when synced_at is null then 1 else 0 end')
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->value('id');

        DB::table($table)
            ->where('trackable_type', $duplicate->trackable_type)
            ->where('trackable_id', $duplicate->trackable_id)
            ->whereNull('source')
            ->where('id', '!=', $keepId)
            ->delete();
    });
```

**2. Backfill the remaining `NULL` sources.** An old `NULL`-source row holds the automatic lifecycle stamps *and* any sourceless syncs you made — they shared a row. Split them by how the row was actually used: rows that were synced belong on the `'default'` source (where sourceless calls now read and write), untouched stamp rows belong on `'_lifecycle'`:

```sql
-- sourceless syncs → 'default'
UPDATE sync_tracked_entities SET source = 'default' WHERE source IS NULL AND synced_at IS NOT NULL;

-- remaining pure lifecycle-stamp rows → '_lifecycle'
UPDATE sync_tracked_entities SET source = '_lifecycle' WHERE source IS NULL;
```

If a backfilled row should live under a real source name instead, rename it now (`UPDATE ... SET source = 'your-source-name' WHERE ...`).

**3. Re-publish and run the migrations:**

```bash
php artisan vendor:publish --provider="WizardingCode\FlowNetwork\SyncTracker\SyncTrackerServiceProvider" --tag="migrations"
php artisan migrate
```


### API mapping

| Deprecated | Replacement |
|---|---|
| `$model->markAsSynced($id, $source, $meta)` | `$model->syncData($source)->markAsSynced($id, $meta)` |
| `$model->setSyncMetadata($meta, $source)` | `$model->syncData($source)->setSyncMetadata($meta)` |
| `$model->mergeSyncMetadata($meta, $source)` | `$model->syncData($source)->mergeSyncMetadata($meta)` |
| `$model->getExternalIdFromSource($source)` | `$model->syncData($source)->external_id` |
| `$model->getExternalId()` | `$model->syncData($source)->external_id` |
| `$model->getSyncMetadata()` | `$model->syncData($source)->metadata` |
| `$model->isSynced()` | `$model->syncData($source)->isSynced()` |
| `$model->getSyncSource()` | inspect `$model->syncTrackers()` — "the source" is ambiguous under multi-source |
| `$model->syncTracking` (relation) | `$model->syncData($source)` / `$model->syncTrackers()` |

The `SyncTracker` facade keeps its signatures; `getSyncInfo()` and `isSynced()` additionally accept an optional `$source`.

### AI upgrade prompt

Paste this into your AI coding agent to migrate an application:

```text
This app uses the andreagroferreira/laravel-sync-tracker package, which moved
to a source-scoped API. Migrate all usages:

1. Find every call to these deprecated methods on models using the
   HasSyncTracking trait: markAsSynced(, setSyncMetadata(, mergeSyncMetadata(,
   getExternalIdFromSource(, getExternalId(, getSyncMetadata(, getSyncSource(,
   isSynced(, and any use of the ->syncTracking relation. Also find
   SyncTracker:: facade calls.

2. Rewrite each to the source-scoped form:
   $model->markAsSynced($id, $src, $meta)  →  $model->syncData($src)->markAsSynced($id, $meta)
   $model->setSyncMetadata($meta, $src)    →  $model->syncData($src)->setSyncMetadata($meta)
   $model->mergeSyncMetadata($meta, $src)  →  $model->syncData($src)->mergeSyncMetadata($meta)
   $model->getExternalIdFromSource($src)   →  $model->syncData($src)->external_id
   $model->getExternalId()                 →  $model->syncData($src)->external_id
   $model->getSyncMetadata()               →  $model->syncData($src)->metadata
   $model->isSynced()                      →  $model->syncData($src)->isSynced()
   $model->syncTracking                    →  $model->syncData($src) (single source)
                                              or $model->syncTrackers() (all rows)

3. Empty-string sources now throw. Call sites passing no source (or null)
   keep working when the allow_empty_source config option is enabled (the
   default) — they resolve to the 'default' source. If the app disables that
   option or wants explicit sources, STOP and ask the developer which source
   name each sourceless call site syncs with — never invent one.

4. Replace queries filtering whereNull('source') on the sync tracking table:
   sourceless sync data now lives on the 'default' source, the automatic
   created/updated/deleted stamps on '_lifecycle'
   (SyncTrackedEntity::DEFAULT_SOURCE / ::LIFECYCLE_SOURCE). Exclude the
   lifecycle row from listings with the withoutLifecycle() scope.

5. The EntitySynced event now also fires when syncing via the trait. Review
   listeners for double-handling.

6. Run the app's test suite and report anything you could not migrate
   mechanically.
```
