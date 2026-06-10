<?php

use Illuminate\Support\Carbon;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Multi-source tracking
|--------------------------------------------------------------------------
|
| These tests describe the intended behaviour of tracking a single model
| against multiple sources at once. They go through the deprecated
| delegates where the old API is the point — the delegates must keep the
| same per-source behaviour as the syncData() API they forward to.
|
*/

it('markAsSynced tracks each source as its own row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1');
    $model->markAsSynced('ext-2', 'source-2');

    expect($model->syncTrackers()->withoutLifecycle()->count())->toBe(2);
    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1');
    expect($model->getExternalIdFromSource('source-2'))->toBe('ext-2');
});

it('markAsSynced updates the existing row when re-syncing the same source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1');
    $model->markAsSynced('ext-1-updated', 'source-1');

    expect($model->syncTrackers()->where('source', 'source-1')->count())->toBe(1);
    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1-updated');
});

it('markAsSynced via the facade tracks each source as its own row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-1', 'source-1');
    SyncTracker::markAsSynced($model, 'ext-2', 'source-2');

    expect($model->syncTrackers()->withoutLifecycle()->count())->toBe(2);
});

it('findByExternalId resolves the model matching the source', function (string $externalId, string $source, ?string $expected) {
    $a = TestModel::create(['name' => 'A']);
    $b = TestModel::create(['name' => 'B']);
    $a->markAsSynced('shared-id', 'source-1');
    $b->markAsSynced('shared-id', 'source-2');

    $found = TestModel::findByExternalId($externalId, $source);

    if ($expected === null) {
        expect($found)->toBeNull();
    } else {
        expect($found?->id)->toBe($expected === 'A' ? $a->id : $b->id);
    }
})->with([
    'source-1 resolves to A' => ['shared-id', 'source-1', 'A'],
    'source-2 resolves to B' => ['shared-id', 'source-2', 'B'],
    'unknown source resolves to null' => ['shared-id', 'unknown', null],
    'missing external id resolves to null' => ['missing', 'source-1', null],
]);

it('findByExternalId returns null when the tracked model row is gone', function () {
    $model = TestModel::create(['name' => 'Test Model']);
    $model->markAsSynced('ext-1', 'source-1');

    // Hard-delete the trackable via the query builder so no model events fire,
    // leaving the tracking row orphaned.
    TestModel::query()->whereKey($model->id)->delete();

    expect(TestModel::findByExternalId('ext-1', 'source-1'))->toBeNull();
});

it('orderByMostRecentlySynced sorts the lifecycle row last', function () {
    // PostgreSQL sorts NULL first on a DESC order (MySQL/SQLite sort it
    // last), so the ordering must spell the null check out rather than
    // rely on plain `order by synced_at desc`. Creating the model also
    // auto-creates a lifecycle tracking row with a NULL synced_at.
    $model = TestModel::create(['name' => 'Test Model']);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $model->markAsSynced('ext-old', 'source-old');

    Carbon::setTestNow('2026-01-02 10:00:00');
    $model->markAsSynced('ext-new', 'source-new');

    Carbon::setTestNow();

    // Most recently synced first, the lifecycle row always last.
    expect($model->syncTrackers()->orderByMostRecentlySynced()->pluck('source')->all())
        ->toBe(['source-new', 'source-old', SyncTrackedEntity::LIFECYCLE_SOURCE]);

    // The deprecated single-source getters read from the top of that order.
    expect($model->fresh()->getExternalId())->toBe('ext-new');
    expect($model->fresh()->isSynced())->toBeTrue();

    // The facade's sourceless lookup reads the same order, excluding the
    // lifecycle row.
    expect(SyncTracker::getSyncInfo($model)->external_id)->toBe('ext-new');
});

it('setSyncMetadata keeps metadata separate per source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['k' => 'val-1'], 'source-1');
    $model->setSyncMetadata(['k' => 'val-2'], 'source-2');

    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['k' => 'val-1']);
    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['k' => 'val-2']);
});

it('mergeSyncMetadata merges into the requested source only', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['a' => 1], 'source-1');
    $model->setSyncMetadata(['b' => 2], 'source-2');

    $model->mergeSyncMetadata(['c' => 3], 'source-2');

    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['b' => 2, 'c' => 3]);
    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['a' => 1]);
});

it('mergeSyncMetadata targets the requested source even when another source synced more recently', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $model->markAsSynced('ext-1', 'source-1', ['a' => 1]);

    Carbon::setTestNow('2026-01-02 10:00:00');
    $model->markAsSynced('ext-2', 'source-2', ['b' => 2]);

    Carbon::setTestNow();

    $model->mergeSyncMetadata(['c' => 3], 'source-1');

    // source-1 keeps its own metadata plus the merged key...
    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['a' => 1, 'c' => 3]);

    // ...and the more recently synced source-2 row stays untouched.
    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['b' => 2]);
});

it('mergeSyncMetadata creates the row for a previously unsynced source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1', ['a' => 1]);

    $model->mergeSyncMetadata(['fresh' => true], 'source-2');

    // Only the merged keys — nothing inherited from another source's row.
    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['fresh' => true]);
});
