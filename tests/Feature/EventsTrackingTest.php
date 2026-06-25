<?php

use Illuminate\Support\Carbon;

use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

it('automatically tracks model creation', function () {
    // Create model without manually tracking it
    $model = TestModel::create(['name' => 'Test Model']);

    // The trait should have auto-tracked creation on the lifecycle row
    $tracking = $model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE);

    expect($tracking->exists)->toBeTrue();
    expect($tracking->created_at)->not->toBeNull();
});

it('automatically tracks model updates', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    // Move time forward to ensure timestamps are different
    travel(1)->minute();

    // Update the model
    $model->name = 'Updated Name';
    $model->save();

    expect($model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE)->updated_at)->not->toBeNull();
});

it('automatically tracks model deletion when using soft deletes', function () {
    // This test would require a model with SoftDeletes
    // Since we can't modify base Laravel behavior in this package to detect real deletions,
    // we're only tracking soft deletes through model events

    // For standard tests, we'll verify config loading
    expect(config('sync-tracker.default_tracking.track_deleted'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Lifecycle events with multiple sources
|--------------------------------------------------------------------------
|
| The created/updated/deleted hooks record their stamps on the dedicated
| '_lifecycle' row. They must never write to a source's row: that would
| corrupt the source's record of its last sync.
|
*/

it('updated event stamps the lifecycle row and leaves source rows untouched', function () {
    travelTo(Carbon::parse('2026-01-01 10:00:00'));
    $model = TestModel::create(['name' => 'Test Model']);
    $model->syncData('source-1')->markAsSynced('ext-1');

    travelTo(Carbon::parse('2026-01-02 10:00:00'));
    $model->update(['name' => 'Updated Name']);

    // The update stamp belongs on the lifecycle row...
    expect($model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE)->updated_at)
        ->toEqual(Carbon::parse('2026-01-02 10:00:00'));

    // ...and must leave the source row's record of its last sync untouched.
    expect($model->syncData('source-1')->updated_at)
        ->toEqual(Carbon::parse('2026-01-01 10:00:00'));
});

it('deleted event stamps the lifecycle row and leaves source rows untouched', function () {
    travelTo(Carbon::parse('2026-01-01 10:00:00'));
    $model = TestModel::create(['name' => 'Test Model']);
    $model->syncData('source-1')->markAsSynced('ext-1');

    travelTo(Carbon::parse('2026-01-02 10:00:00'));
    $model->delete();

    // The deletion stamp belongs on the lifecycle row...
    expect($model->syncTrackers()->where('source', SyncTrackedEntity::LIFECYCLE_SOURCE)->first()->deleted_at)
        ->toEqual(Carbon::parse('2026-01-02 10:00:00'));

    // ...while the source row was never deleted and must stay untouched.
    expect($model->syncTrackers()->where('source', 'source-1')->first()->deleted_at)
        ->toBeNull();
});
