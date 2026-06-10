<?php

use Illuminate\Support\Facades\Event;
use WizardingCode\FlowNetwork\SyncTracker\Events\EntitySynced;
use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Source-scoped sync data
|--------------------------------------------------------------------------
|
| syncData($source) resolves the SyncTrackedEntity for one source. All sync
| reads and writes live on that entity, so once it is resolved nothing else
| needs to think about sources.
|
*/

it('syncData returns an unsaved entity without creating a row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $entity = $model->syncData('source-1');

    expect($entity->exists)->toBeFalse();
    expect($entity->isSynced())->toBeFalse();
    expect($entity->external_id)->toBeNull();
    expect($model->syncTrackers()->where('source', 'source-1')->count())->toBe(0);
});

it('syncData->markAsSynced tracks each source as its own row', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->markAsSynced('ext-1');
    $model->syncData('source-2')->markAsSynced('ext-2');

    expect($model->syncTrackers()->withoutLifecycle()->count())->toBe(2);
    expect($model->syncData('source-1')->external_id)->toBe('ext-1');
    expect($model->syncData('source-2')->external_id)->toBe('ext-2');
});

it('syncData->markAsSynced updates the existing row when re-syncing the same source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->markAsSynced('ext-1');
    $model->syncData('source-1')->markAsSynced('ext-1-updated');

    expect($model->syncTrackers()->where('source', 'source-1')->count())->toBe(1);
    expect($model->syncData('source-1')->external_id)->toBe('ext-1-updated');
});

it('syncData->markAsSynced without arguments preserves the external id and metadata', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('erp')->markAsSynced('ext-xyz', ['foo' => 'bar']);

    // Re-sync to update the timestamp without passing the data again
    $model->syncData('erp')->markAsSynced();

    $entity = $model->syncData('erp');
    expect($entity->isSynced())->toBeTrue();
    expect($entity->external_id)->toBe('ext-xyz');
    expect($entity->metadata)->toBe(['foo' => 'bar']);
});

it('syncData->setSyncMetadata keeps metadata separate per source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->setSyncMetadata(['k' => 'val-1']);
    $model->syncData('source-2')->setSyncMetadata(['k' => 'val-2']);

    expect($model->syncData('source-1')->metadata)->toBe(['k' => 'val-1']);
    expect($model->syncData('source-2')->metadata)->toBe(['k' => 'val-2']);
});

it('syncData->mergeSyncMetadata merges into the requested source only', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->setSyncMetadata(['a' => 1]);
    $model->syncData('source-2')->setSyncMetadata(['b' => 2]);

    $model->syncData('source-2')->mergeSyncMetadata(['c' => 3]);

    expect($model->syncData('source-2')->metadata)->toBe(['b' => 2, 'c' => 3]);
    expect($model->syncData('source-1')->metadata)->toBe(['a' => 1]);
});

it('syncData->mergeSyncMetadata creates the row for a previously unsynced source', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->markAsSynced('ext-1', ['a' => 1]);

    $model->syncData('source-2')->mergeSyncMetadata(['fresh' => true]);

    // Only the merged keys — nothing inherited from another source's row.
    expect($model->syncData('source-2')->metadata)->toBe(['fresh' => true]);
});

it('syncData throws for an empty source string', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('');
})->throws(EmptySourceException::class);

it('syncData throws for an unsaved model', function () {
    $model = new TestModel(['name' => 'Test Model']);

    $model->syncData('source-1');
})->throws(InvalidArgumentException::class);

it('syncData preloads the trackable relation with the same model instance', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $entity = $model->syncData('source-1');

    expect($entity->relationLoaded('trackable'))->toBeTrue();
    expect($entity->trackable)->toBe($model);
});

it('syncData can target the lifecycle row explicitly', function () {
    $model = TestModel::create(['name' => 'Test Model']);

    $entity = $model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE);

    expect($entity->exists)->toBeTrue();
    expect($entity->isLifecycle())->toBeTrue();
    expect($entity->created_at)->not->toBeNull();
});

it('syncData->markAsSynced dispatches EntitySynced', function () {
    // Fake only EntitySynced so the model lifecycle events still reach the
    // trait's automatic tracking hooks.
    Event::fake([EntitySynced::class]);

    $model = TestModel::create(['name' => 'Test Model']);

    $model->syncData('source-1')->markAsSynced('ext-1');

    Event::assertDispatched(EntitySynced::class, function (EntitySynced $event) use ($model) {
        return $event->model->is($model) && $event->syncInfo->source === 'source-1';
    });
});

it('markAsSynced via the facade dispatches EntitySynced', function () {
    Event::fake([EntitySynced::class]);

    $model = TestModel::create(['name' => 'Test Model']);

    SyncTracker::markAsSynced($model, 'ext-1', 'source-1');

    Event::assertDispatched(EntitySynced::class, function (EntitySynced $event) use ($model) {
        return $event->model->is($model) && $event->syncInfo->source === 'source-1';
    });
});
