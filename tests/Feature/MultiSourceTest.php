<?php

use Illuminate\Support\Carbon;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
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
| against multiple sources at once.
|
*/

it('tracks the same model against multiple sources independently', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1');
    $model->markAsSynced('ext-2', 'source-2');

    expect($model->syncTrackers()->whereNotNull('source')->count())->toBe(2);
    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1');
    expect($model->getExternalIdFromSource('source-2'))->toBe('ext-2');
});

it('updates the existing row when re-syncing the same source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1');
    $model->markAsSynced('ext-1-updated', 'source-1');

    expect($model->syncTrackers()->where('source', 'source-1')->count())->toBe(1);
    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1-updated');
});

it('tracks multiple sources via the facade independently', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-1', 'source-1');
    SyncTracker::markAsSynced($model, 'ext-2', 'source-2');

    expect($model->syncTrackers()->whereNotNull('source')->count())->toBe(2);
});

it('finds the right model by external id and source', function (string $externalId, string $source, ?string $expected) {
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

it('matches a sourceless tracking row when source is null', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('no-src', null);

    expect(TestModel::findByExternalId('no-src')?->id)->toBe($model->id);
});

it('returns null when the trackable no longer exists', function () {
    $model = TestModel::create(['name' => 'Test Model']);
    $model->markAsSynced('ext-1', 'source-1');

    // Hard-delete the trackable via the query builder so no model events fire,
    // leaving the tracking row orphaned.
    TestModel::query()->whereKey($model->id)->delete();

    expect(TestModel::findByExternalId('ext-1', 'source-1'))->toBeNull();
});

it('orders tracking rows with explicit null handling so lifecycle rows sort last on every database', function () {
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
        ->toBe(['source-new', 'source-old', null]);

    // The single-source getters read from the top of that order.
    expect($model->fresh()->getExternalId())->toBe('ext-new');
    expect($model->fresh()->isSynced())->toBeTrue();
});

it('keeps metadata separate per source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['k' => 'val-1'], 'source-1');
    $model->setSyncMetadata(['k' => 'val-2'], 'source-2');

    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['k' => 'val-1']);
    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['k' => 'val-2']);
});

it('merges metadata against the correct source only', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['a' => 1], 'source-1');
    $model->setSyncMetadata(['b' => 2], 'source-2');

    $model->mergeSyncMetadata(['c' => 3], 'source-2');

    expect($model->syncTrackers()->where('source', 'source-2')->first()->metadata)->toBe(['b' => 2, 'c' => 3]);
    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['a' => 1]);
});
