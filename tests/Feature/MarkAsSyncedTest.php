<?php

use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;

// Make sure to use the TestCase to have Laravel set up
uses(WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase::class);

it('can mark a model as synced using facade', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-123', 'api');

    expect(SyncTracker::isSynced($model))->toBeTrue();
    expect(SyncTracker::getSyncInfo($model)->external_id)->toBe('ext-123');
    expect(SyncTracker::getSyncInfo($model)->source)->toBe('api');
});

it('can mark a model as synced using trait', function () {
    $model = TestModel::create(['name' => 'Test With Trait']);

    $model->markAsSynced('ext-xyz', 'erp', ['foo' => 'bar']);

    expect($model->isSynced())->toBeTrue();
    expect($model->getExternalId())->toBe('ext-xyz');
    expect($model->getSyncSource())->toBe('erp');
    expect($model->getSyncMetadata())->toBe(['foo' => 'bar']);
});

it('can mark a model as synced without metadata using trait', function () {
    $model = TestModel::create(['name' => 'Test With Trait']);

    $model->markAsSynced('ext-xyz', 'erp');

    expect($model->isSynced())->toBeTrue();
    expect($model->getExternalId())->toBe('ext-xyz');
    expect($model->getSyncSource())->toBe('erp');
    expect($model->getSyncMetadata())->toBe(null);
});

it('can mark a model as synced without overwriting metadata using trait', function () {
    $model = TestModel::create(['name' => 'Test With Trait']);

    // Mark as synced and set some metadata
    $model->markAsSynced('ext-xyz', 'erp', ['foo' => 'bar']);
    expect($model->getSyncMetadata())->toBe(['foo' => 'bar']);

    // Mark as synced again without setting it
    $model->markAsSynced('ext-xyz', 'erp');

    // Check the metadata was not changed
    expect($model->getSyncMetadata())->toBe(['foo' => 'bar']);
});
