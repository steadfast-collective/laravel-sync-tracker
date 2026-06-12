<?php

use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

it('can mark a model as synced using facade', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-123', 'api');

    expect(SyncTracker::isSynced($model))->toBeTrue();
    expect(SyncTracker::getSyncInfo($model)->external_id)->toBe('ext-123');
    expect(SyncTracker::getSyncInfo($model)->source)->toBe('api');
});

it('markAsSynced with only a source refreshes the sync without clearing existing data', function () {
    $model = TestModel::create(['name' => 'Test With Trait']);

    // Mark as synced with all the details (id, source, metadata)
    $model->syncData('erp')->markAsSynced('ext-xyz', ['foo' => 'bar']);

    // Mark as synced to update the timestamps, but not the related data
    $model->syncData('erp')->markAsSynced();

    // Check the data is still present and correct
    $entity = $model->syncData('erp');
    expect($entity->isSynced())->toBeTrue();
    expect($entity->external_id)->toBe('ext-xyz');
    expect($entity->metadata)->toBe(['foo' => 'bar']);
});

it('markAsSynced without metadata does not overwrite existing metadata', function () {
    $model = TestModel::create(['name' => 'Test With Trait']);

    // Mark as synced and set some metadata
    $model->syncData('erp')->markAsSynced('ext-xyz', ['foo' => 'bar']);
    expect($model->syncData('erp')->metadata)->toBe(['foo' => 'bar']);

    // Mark as synced again without setting it
    $model->syncData('erp')->markAsSynced('ext-xyz');

    // Check the metadata was not changed
    expect($model->syncData('erp')->metadata)->toBe(['foo' => 'bar']);
});

it('markAsSynced via the facade without metadata does not overwrite existing metadata', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-123', 'api', ['foo' => 'bar']);

    // Re-syncing without metadata used to wipe the stored metadata.
    SyncTracker::markAsSynced($model, 'ext-123', 'api');

    expect($model->syncData('api')->metadata)->toBe(['foo' => 'bar']);
});
