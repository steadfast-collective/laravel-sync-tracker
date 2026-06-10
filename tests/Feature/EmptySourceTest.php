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

it('markAsSynced throws without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1');
})->throws(EmptySourceException::class);

it('setSyncMetadata throws without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['a' => 1]);
})->throws(EmptySourceException::class);

it('mergeSyncMetadata throws without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->mergeSyncMetadata(['a' => 1]);
})->throws(EmptySourceException::class);

it('findByExternalId throws without a source', function () {
    TestModel::findByExternalId('ext-1');
})->throws(EmptySourceException::class);

it('markAsSynced via the facade throws without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-1');
})->throws(EmptySourceException::class);

it('track via the facade throws without a source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::track($model, ['external_id' => 'ext-1']);
})->throws(EmptySourceException::class);

it('sync APIs work normally when a source is provided', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1', ['a' => 1]);
    $model->setSyncMetadata(['b' => 2], 'source-1');
    $model->mergeSyncMetadata(['c' => 3], 'source-1');

    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1');
    expect(TestModel::findByExternalId('ext-1', 'source-1')?->id)->toBe($model->id);
    expect($model->syncTrackers()->where('source', 'source-1')->first()->metadata)->toBe(['b' => 2, 'c' => 3]);
});

it('lifecycle tracking is exempt and records on the sourceless row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->update(['name' => 'Updated Name']);

    expect($model->syncTrackers()->whereNull('source')->count())->toBe(1);
});

it('allow_empty_source = true permits omitting the source', function () {
    config(['sync-tracker.allow_empty_source' => true]);

    $model = TestModel::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1');

    expect(TestModel::findByExternalId('ext-1')?->id)->toBe($model->id);
});
