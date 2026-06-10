<?php

use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Empty sources
|--------------------------------------------------------------------------
|
| With sync-tracker.allow_empty_source enabled (the default) not passing a source
| sets the source to 'default' - disabling it throws an exception if a source
| is not set.
|
*/

describe('how allow_empty_source is handled when it is false', function () {
    beforeEach(function () {
        config(['sync-tracker.allow_empty_source' => false]);
    });

    it('syncData throws without a source', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->syncData();
    })->throws(EmptySourceException::class);

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

        expect($model->syncData('source-1')->external_id)->toBe('ext-1');
        expect(TestModel::findByExternalId('ext-1', 'source-1')?->id)->toBe($model->id);
        expect($model->syncData('source-1')->metadata)->toBe(['b' => 2, 'c' => 3]);
    });

    it('lifecycle tracking is exempt and records on the lifecycle row', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->update(['name' => 'Updated Name']);

        expect($model->syncTrackers()->where('source', SyncTrackedEntity::LIFECYCLE_SOURCE)->count())->toBe(1);
    });
});

describe('how allow_empty_source is handled when it is true (the default)', function () {
    it('syncData without a source resolves to the default source', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        expect($model->syncData()->source)->toBe(SyncTrackedEntity::DEFAULT_SOURCE);
        expect($model->syncData()->isLifecycle())->toBeFalse();
    });

    it('markAsSynced without a source records on the default source row', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->markAsSynced('ext-1');

        $entity = $model->syncData(SyncTrackedEntity::DEFAULT_SOURCE);
        expect($entity->isSynced())->toBeTrue();
        expect($entity->external_id)->toBe('ext-1');

        // The lifecycle row only carries the automatic stamps.
        expect($model->syncData(SyncTrackedEntity::LIFECYCLE_SOURCE)->isSynced())->toBeFalse();
    });

    it('findByExternalId without a source matches the default source row', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->markAsSynced('no-src');

        expect(TestModel::findByExternalId('no-src')?->id)->toBe($model->id);
    });
});

describe('empty source strings', function () {
    it('syncData always throws for an empty source string', function (bool $allowEmpty) {
        config(['sync-tracker.allow_empty_source' => $allowEmpty]);

        $model = TestModel::create(['name' => 'Test Model']);

        $model->syncData('');
    })->with([
        'empty sources allowed' => [true],
        'empty sources disabled' => [false],
    ])->throws(EmptySourceException::class);

    it('markAsSynced always throws for an empty source string', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->markAsSynced('ext-1', '');
    })->throws(EmptySourceException::class);

    it('findByExternalId always throws for an empty source string', function () {
        TestModel::findByExternalId('ext-1', '');
    })->throws(EmptySourceException::class);

    it('markAsSynced always throws for a whitespace-only source string', function () {
        $model = TestModel::create(['name' => 'Test Model']);

        $model->markAsSynced('ext-1', '  ');
    })->throws(EmptySourceException::class);
});
