<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $trackable_type
 * @property int $trackable_id
 * @property string|null $external_id
 * @property string|null $log_level
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property array<array-key, mixed>|null $metadata
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereTrackabletype($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereTrackableid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereExternalid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereLoglevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereSyncedat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereCreatedat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereUpdatedat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereDeletedat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SyncTrackedEntity whereMetadata($value)
 */
class SyncTrackedEntity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'external_id',
        'source',
        'synced_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('sync-tracker.table_name', 'sync_tracked_entities'));
    }

    /**
     * Get the trackable model.
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
