<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

class SyncMetadataTest extends TestCase
{
    #[Test]
    public function set_sync_metadata_stores_the_metadata()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $metadata = [
            'foo' => 'bar',
        ];
        $model->syncData('erp')->setSyncMetadata($metadata);

        $this->assertEquals($metadata, $model->syncData('erp')->metadata);
    }

    #[Test]
    public function merge_sync_metadata_merges_new_keys_over_existing()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $metadata = [
            'foo' => 'bar',
            'hello' => 'world',
        ];
        $model->syncData('erp')->setSyncMetadata($metadata);

        $newMetadata = [
            'foo' => 'par',
            'goodbye' => 'universe',
        ];
        $model->syncData('erp')->mergeSyncMetadata($newMetadata);

        $this->assertEquals([
            'foo' => 'par',
            'hello' => 'world',
            'goodbye' => 'universe',
        ], $model->syncData('erp')->metadata);
    }
}
