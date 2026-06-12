<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WizardingCode\FlowNetwork\SyncTracker\Traits\DeprecatedSyncTrackerMethods;
use WizardingCode\FlowNetwork\SyncTracker\Traits\HasSyncTracking;

/**
 * @property int $id
 * @property string $name
 */
class TestModelWithDeprecatedMethods extends Model
{
    use DeprecatedSyncTrackerMethods;
    use HasSyncTracking;

    protected $table = 'test_models';

    protected $fillable = ['name'];
}
