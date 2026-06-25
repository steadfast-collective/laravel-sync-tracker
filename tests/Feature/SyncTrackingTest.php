<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WizardingCode\FlowNetwork\SyncTracker\Facades\SyncTracker;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

class SyncTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_mark_a_model_as_synced()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        SyncTracker::markAsSynced($model, 'ext-123', 'api');

        $this->assertTrue(SyncTracker::isSynced($model));
        $this->assertEquals('ext-123', SyncTracker::getSyncInfo($model)->external_id);
        $this->assertEquals('api', SyncTracker::getSyncInfo($model)->source);
    }

    #[Test]
    public function it_can_get_sync_info_for_a_specific_source()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        SyncTracker::markAsSynced($model, 'ext-1', 'crm');
        SyncTracker::markAsSynced($model, 'ext-2', 'erp');

        $this->assertEquals('ext-1', SyncTracker::getSyncInfo($model, 'crm')->external_id);
        $this->assertEquals('ext-2', SyncTracker::getSyncInfo($model, 'erp')->external_id);
        $this->assertNull(SyncTracker::getSyncInfo($model, 'unknown'));
        $this->assertTrue(SyncTracker::isSynced($model, 'crm'));
        $this->assertFalse(SyncTracker::isSynced($model, 'unknown'));
    }

    #[Test]
    public function it_can_find_a_model_by_external_id()
    {
        $model = TestModel::create(['name' => 'Test Model']);
        SyncTracker::markAsSynced($model, 'ext-abc', 'crm');

        /** @var ?TestModel $found */
        $found = SyncTracker::findByExternalId('ext-abc', 'crm', TestModel::class);

        $this->assertNotNull($found);
        $this->assertEquals($model->id, $found->id);
    }

    #[Test]
    public function it_can_use_sync_data_from_the_trait()
    {
        $model = TestModel::create(['name' => 'Test With Trait']);

        $model->syncData('erp')->markAsSynced('ext-xyz', ['foo' => 'bar']);

        $entity = $model->syncData('erp');
        $this->assertTrue($entity->isSynced());
        $this->assertEquals('ext-xyz', $entity->external_id);
        $this->assertEquals('erp', $entity->source);
        $this->assertEquals(['foo' => 'bar'], $entity->metadata);
    }
}
