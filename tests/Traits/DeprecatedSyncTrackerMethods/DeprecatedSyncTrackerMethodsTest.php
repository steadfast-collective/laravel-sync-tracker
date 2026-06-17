<?php

use Illuminate\Support\Carbon;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModelWithDeprecatedMethods;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

uses(TestCase::class);

it('markAsSynced delegates to syncData and deprecated getters expose the latest sync row', function () {
    $model = TestModelWithDeprecatedMethods::create(['name' => 'Test With Trait']);

    $model->markAsSynced('ext-xyz', 'erp', ['foo' => 'bar']);

    expect($model->syncData('erp')->isSynced())->toBeTrue();
    expect($model->syncData('erp')->external_id)->toBe('ext-xyz');

    // Deprecated any-source getters read from the most recently synced row.
    expect($model->isSynced())->toBeTrue();
    expect($model->getExternalId())->toBe('ext-xyz');
    expect($model->getSyncSource())->toBe('erp');
    expect($model->getSyncMetadata())->toBe(['foo' => 'bar']);
});

it('setSyncMetadata and mergeSyncMetadata delegates target the requested source', function () {
    $model = TestModelWithDeprecatedMethods::create(['name' => 'Test Model']);

    $model->setSyncMetadata(['foo' => 'bar'], 'erp');
    $model->mergeSyncMetadata(['baz' => 'qux'], 'erp');

    expect($model->syncData('erp')->metadata)->toEqual(['foo' => 'bar', 'baz' => 'qux']);
    expect($model->getSyncMetadata())->toEqual(['foo' => 'bar', 'baz' => 'qux']);
});

it('getExternalIdFromSource returns the external id for the requested source', function () {
    $model = TestModelWithDeprecatedMethods::create(['name' => 'Test Model']);

    $model->markAsSynced('ext-1', 'source-1');
    $model->markAsSynced('ext-2', 'source-2');

    expect($model->getExternalIdFromSource('source-1'))->toBe('ext-1');
    expect($model->getExternalIdFromSource('source-2'))->toBe('ext-2');
});

it('any-source deprecated getters read from the most recently synced source', function () {
    $model = TestModelWithDeprecatedMethods::create(['name' => 'Test Model']);

    Carbon::setTestNow('2026-01-01 10:00:00');
    $model->markAsSynced('ext-old', 'source-old', ['old' => true]);

    Carbon::setTestNow('2026-01-02 10:00:00');
    $model->markAsSynced('ext-new', 'source-new', ['new' => true]);

    Carbon::setTestNow();

    expect($model->fresh()->getExternalId())->toBe('ext-new');
    expect($model->fresh()->isSynced())->toBeTrue();
    expect($model->fresh()->getSyncSource())->toBe('source-new');
    expect($model->fresh()->getSyncMetadata())->toBe(['new' => true]);
});
