<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use WizardingCode\FlowNetwork\SyncTracker\Events\EntitySynced;
use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;

/**
 * @property int $id
 * @property string $trackable_type
 * @property int $trackable_id
 * @property string|null $external_id
 * @property string|null $log_level
 * @property string $source
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property array<array-key, mixed>|null $metadata
 * @property-read Model|null $trackable
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
     * The `source` value for automatic lifecycle SyncTrackedEntity
     */
    public const LIFECYCLE_SOURCE = '_lifecycle';

    /**
     * The `source` value used when a tracking call omits the source.
     */
    public const DEFAULT_SOURCE = 'default';

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
     * Resolve the tracking entity for a model and source, creating an
     * unsaved one when the model has not been tracked against that source
     * yet. This is the single place where source scoping happens — every
     * read and write on the returned entity is already scoped.
     *
     * An omitted source resolves to the 'default' source (if the
     * allow_empty_source config option permits omitting it).
     */
    public static function for(Model $model, ?string $source = null): self
    {
        EmptySourceException::throwIfDisallowed($source);

        $source ??= self::DEFAULT_SOURCE;

        throw_unless(
            $model->exists,
            InvalidArgumentException::class,
            'Cannot track sync state for an unsaved model.'
        );

        $entity = static::firstOrNew([
            'trackable_type' => $model->getMorphClass(),
            'trackable_id' => $model->getKey(),
            'source' => $source,
        ]);

        $entity->setRelation('trackable', $model);

        return $entity;
    }

    /**
     * Get the trackable model.
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark this entity as synced. Only overwrites the external ID and
     * metadata when new values are given.
     */
    public function markAsSynced(?string $externalId = null, ?array $metadata = null): static
    {
        $this->synced_at = now();

        if ($externalId !== null) {
            $this->external_id = $externalId;
        }

        if ($metadata !== null) {
            $this->metadata = $metadata;
        }

        $this->saveTracked();

        // The trackable can be null when this entity was loaded directly and
        // the tracked model has since been hard-deleted.
        if ($this->trackable !== null) {
            event(new EntitySynced($this->trackable, $this));
        }

        return $this;
    }

    /**
     * Overwrite the sync metadata with the given metadata.
     */
    public function setSyncMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this->saveTracked();
    }

    /**
     * Update the sync metadata with the given fields. Don't change the other fields.
     */
    public function mergeSyncMetadata(array $metadata): static
    {
        $this->metadata = [
            ...($this->metadata ?? []),
            ...$metadata,
        ];

        return $this->saveTracked();
    }

    /**
     * Check if this entity has been synced.
     */
    public function isSynced(): bool
    {
        return $this->synced_at !== null;
    }

    /**
     * Check if this is the automatic lifecycle tracking row.
     */
    public function isLifecycle(): bool
    {
        return $this->source === self::LIFECYCLE_SOURCE;
    }

    /**
     * Save, recovering from a concurrent first-write of the same
     * (trackable, source) row: the unique index rejects the duplicate
     * insert, so adopt the winning row and apply this entity's values to it.
     */
    public function saveTracked(): static
    {
        try {
            $this->save();
        } catch (QueryException $e) {
            // QueryException rather than UniqueConstraintViolationException —
            // the dedicated subclass doesn't exist on the oldest supported
            // framework version.
            $existing = static::query()->where([
                'trackable_type' => $this->trackable_type,
                'trackable_id' => $this->trackable_id,
                'source' => $this->source,
            ])->first();

            throw_if($this->exists || $existing === null, $e);

            $this->id = $existing->id;
            $this->exists = true;
            $this->save();
        }

        return $this;
    }

    /**
     * Exclude the automatic lifecycle tracking rows.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutLifecycle(Builder $query): Builder
    {
        return $query->where('source', '!=', self::LIFECYCLE_SOURCE);
    }

    /**
     * Order the query so the most recently synced row comes first.
     *
     * Rows with a NULL synced_at (auto-created lifecycle rows) must sort
     * last on every database: PostgreSQL treats NULL as the largest value
     * (NULLS FIRST on a DESC order), while MySQL and SQLite treat it as
     * the smallest, so the null check has to be explicit.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderByMostRecentlySynced(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when synced_at is null then 1 else 0 end')
            ->orderByDesc('synced_at')
            ->orderByDesc('id');
    }
}
