<?php

use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

// Make sure to use the TestCase to have Laravel set up
uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| hasExternalId scope
|--------------------------------------------------------------------------
|
| Returns only models with a tracker row carrying an external_id, optionally
| narrowed to a single source. The external_id guard also keeps the
| auto-created '_lifecycle' rows from matching.
|
*/

it('returns only models that have been synced', function () {
    $synced = TestModel::create(['name' => 'Synced']);
    SyncTracker::markAsSynced($synced, 'ext-1', 'crm');

    TestModel::create(['name' => 'Unsynced']);

    expect(TestModel::hasExternalId()->pluck('id')->all())->toBe([$synced->id]);
});

it('narrows to a single source when one is given', function () {
    $crm = TestModel::create(['name' => 'CRM']);
    SyncTracker::markAsSynced($crm, 'ext-a', 'crm');

    $erp = TestModel::create(['name' => 'ERP']);
    SyncTracker::markAsSynced($erp, 'ext-b', 'erp');

    expect(TestModel::hasExternalId('crm')->pluck('id')->all())->toBe([$crm->id]);
    expect(TestModel::hasExternalId('erp')->pluck('id')->all())->toBe([$erp->id]);
});

it('matches a sync to any source when no source is given', function () {
    $crm = TestModel::create(['name' => 'CRM']);
    SyncTracker::markAsSynced($crm, 'ext-a', 'crm');

    $erp = TestModel::create(['name' => 'ERP']);
    SyncTracker::markAsSynced($erp, 'ext-b', 'erp');

    expect(TestModel::hasExternalId()->pluck('id')->all())
        ->toEqualCanonicalizing([$crm->id, $erp->id]);
});

it('excludes models with only an auto-created lifecycle row', function () {
    // create() fires the lifecycle hook, writing a '_lifecycle' tracker row
    // with a null external_id. The model is never synced to a real source.
    TestModel::create(['name' => 'Lifecycle only']);

    expect(TestModel::hasExternalId()->count())->toBe(0);
    expect(TestModel::hasExternalId('crm')->count())->toBe(0);
});

it('excludes a model synced to a different source', function () {
    $model = TestModel::create(['name' => 'CRM only']);
    SyncTracker::markAsSynced($model, 'ext-a', 'crm');

    expect(TestModel::hasExternalId('other')->count())->toBe(0);
});
