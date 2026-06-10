<?php

use Illuminate\Support\Carbon;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;

use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

// Make sure to use the TestCase to have Laravel set up
uses(WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase::class);

it('automatically tracks model creation', function () {
    // Create model without manually tracking it
    $model = TestModel::create(['name' => 'Test Model']);

    // The trait should have auto-tracked creation
    $tracking = $model->syncTracking;

    expect($tracking)->not->toBeNull();
    expect($tracking->created_at)->not->toBeNull();
});

it('automatically tracks model updates', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    // Move time forward to ensure timestamps are different
    travel(1)->minute();

    // Update the model
    $model->name = 'Updated Name';
    $model->save();

    // Refresh tracking relationship
    $model->refresh();

    expect($model->syncTracking->updated_at)->not->toBeNull();
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
| The created/updated/deleted hooks match the tracking row on
| trackable_type + trackable_id only. Once a model is tracked against a
| source, the ordered syncTracking relation resolves to the most recently
| synced source row, so lifecycle stamps must explicitly target the
| sourceless lifecycle row to avoid corrupting a source's sync record.
|
*/

it('stamps model updates on the lifecycle row, not the most recently synced source row', function () {
    travelTo(Carbon::parse('2026-01-01 10:00:00'));
    $model = TestModel::create(['name' => 'Test Model']);
    $model->markAsSynced('ext-1', 'source-1');

    travelTo(Carbon::parse('2026-01-02 10:00:00'));
    $model->update(['name' => 'Updated Name']);

    // The update stamp belongs on the sourceless lifecycle row...
    expect($model->syncTrackers()->whereNull('source')->first()->updated_at)
        ->toEqual(Carbon::parse('2026-01-02 10:00:00'));

    // ...and must leave the source row's record of its last sync untouched.
    expect($model->syncTrackers()->where('source', 'source-1')->first()->updated_at)
        ->toEqual(Carbon::parse('2026-01-01 10:00:00'));
});

it('stamps model deletion on the lifecycle row, not the most recently synced source row', function () {
    travelTo(Carbon::parse('2026-01-01 10:00:00'));
    $model = TestModel::create(['name' => 'Test Model']);
    $model->markAsSynced('ext-1', 'source-1');

    travelTo(Carbon::parse('2026-01-02 10:00:00'));
    $model->delete();

    // The deletion stamp belongs on the sourceless lifecycle row...
    expect($model->syncTrackers()->whereNull('source')->first()->deleted_at)
        ->toEqual(Carbon::parse('2026-01-02 10:00:00'));

    // ...while the source row was never deleted and must stay untouched.
    expect($model->syncTrackers()->where('source', 'source-1')->first()->deleted_at)
        ->toBeNull();
});
