<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Tests\Feature;

use WizardingCode\FlowNetwork\SyncTracker\Tests\Models\TestModel;
use WizardingCode\FlowNetwork\SyncTracker\Tests\TestCase;

class SyncMetadataTest extends TestCase
{
    /** @test */
    public function it_can_set_the_metadata()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $metadata = [
            'foo' => 'bar',
        ];
        $model->setSyncMetadata($metadata);

        $this->assertEquals($metadata, $model->getSyncMetadata());
    }

    /** @test */
    public function it_can_merge_the_metadata()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $metadata = [
            'foo' => 'bar',
            'hello' => 'world',
        ];
        $model->setSyncMetadata($metadata);

        $newMetadata = [
            'foo' => 'par',
            'goodbye' => 'universe',
        ];
        $model->mergeSyncMetadata($newMetadata);

        $this->assertEquals([
            'foo' => 'par',
            'hello' => 'world',
            'goodbye' => 'universe',
        ], $model->getSyncMetadata());
    }
}
