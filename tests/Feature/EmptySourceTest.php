<?php

use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Empty sources disabled
|--------------------------------------------------------------------------
|
| With sync-tracker.allow_empty_source set to false, every sync API that
| accepts a source must throw an EmptySourceException when the source is
| omitted, so a source can never be forgotten by accident. Automatic
| lifecycle tracking always targets the sourceless lifecycle row and is
| exempt.
|
*/

beforeEach(function () {
    config(['sync-tracker.allow_empty_source' => false]);
});

it('throws when marking as synced without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1');
})->throws(EmptySourceException::class);

it('throws when setting metadata without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['a' => 1]);
})->throws(EmptySourceException::class);

it('throws when merging metadata without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->mergeSyncMetadata(['a' => 1]);
})->throws(EmptySourceException::class);

it('throws when finding by external id without a source', function () {
    TestModel::findByExternalId('ext-1');
})->throws(EmptySourceException::class);

it('throws when marking as synced via the facade without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-1');
})->throws(EmptySourceException::class);

it('throws when tracking via the facade without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::track($model, ['external_id' => 'ext-1']);
})->throws(EmptySourceException::class);

it('still syncs normally when a source is provided', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1', ['a' => 1]);
    $model->setSyncMetadata(['b' => 2], 'source-1');
    $model->mergeSyncMetadata(['c' => 3], 'source-1');

    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1');
    expect(TestModel::findByExternalId('ext-1', 'source-1')?->id)->toBe($model->id);
    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['b' => 2, 'c' => 3]);
});

it('still records lifecycle events on the sourceless lifecycle row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->update(['name' => 'Updated Name']);

    expect($model->syncTrackers()->whereNull('source')->count())->toBe(1);
});

it('allows omitting the source when the option is enabled', function () {
    config(['sync-tracker.allow_empty_source' => true]);

    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1');

    expect(TestModel::findByExternalId('ext-1')?->id)->toBe($model->id);
});
