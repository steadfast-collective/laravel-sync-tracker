<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;

trait HasSyncTracking
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootHasSyncTracking()
    {
        // Lifecycle events are recorded on the sourceless lifecycle row.
        // `source` must be part of the match keys: without it the ordered
        // syncTracking relation resolves to the most recently synced source
        // row and the stamp would corrupt that source's sync record.
        static::created(function ($model) {
            if (config('sync-tracker.default_tracking.track_created', true)) {
                $model->syncTracking()->updateOrCreate(
                    ['trackable_type' => get_class($model), 'trackable_id' => $model->getKey(), 'source' => null],
                    ['created_at' => now()]
                );
            }
        });

        static::updated(function ($model) {
            if (config('sync-tracker.default_tracking.track_updated', true)) {
                $model->syncTracking()->updateOrCreate(
                    ['trackable_type' => get_class($model), 'trackable_id' => $model->getKey(), 'source' => null],
                    ['updated_at' => now()]
                );
            }
        });

        static::deleted(function ($model) {
            if (config('sync-tracker.default_tracking.track_deleted', true)) {
                $model->syncTracking()->updateOrCreate(
                    ['trackable_type' => get_class($model), 'trackable_id' => $model->getKey(), 'source' => null],
                    ['deleted_at' => now()]
                );
            }
        });
    }

    public static function findByExternalId(string $externalId, ?string $source = null)
    {
        EmptySourceException::throwIfDisallowed($source);

        $tracking = SyncTrackedEntity::where([
            'external_id' => $externalId,
            'source' => $source,
            'trackable_type' => static::class,
        ])
            ->whereHas('trackable')
            ->first();

        if (! $tracking) {
            return null;
        }

        return $tracking->trackable;
    }

    /**
     * Get the sync tracking information for this model.
     *
     * @return MorphOne<SyncTrackedEntity, $this>
     */
    public function syncTracking(): MorphOne
    {
        // A model may be tracked against several sources. When no source is
        // specified, prefer the most recently synced row (falling back to the
        // newest row) so the single-source getters return real sync data
        // rather than an auto-created lifecycle row.
        return $this->morphOne(SyncTrackedEntity::class, 'trackable')
            ->orderByMostRecentlySynced();
    }

    /**
     * Get all sync tracking entries for this model.
     *
     * @return MorphMany<SyncTrackedEntity, $this>
     */
    public function syncTrackers(): MorphMany
    {
        return $this->morphMany(SyncTrackedEntity::class, 'trackable');
    }

    /**
     * Mark this model as synced.
     */
    public function markAsSynced(?string $externalId = null, ?string $source = null, ?array $metadata = null): SyncTrackedEntity
    {
        EmptySourceException::throwIfDisallowed($source);

        throw_if(
            $this->relationLoaded('syncTracking') && $this->syncTracking->isDirty(),
            'Please save your syncTracking model before using markAsSynced to avoid data loss'
        );

        // The values to update
        $values = [
            'synced_at' => now(),
        ];

        if ($metadata !== null) {
            $values['metadata'] = $metadata;
        }

        if ($externalId !== null) {
            $values['external_id'] = $externalId;
        }

        $return = $this->syncTracking()->updateOrCreate(
            ['trackable_type' => get_class($this), 'trackable_id' => $this->getKey(), 'source' => $source],
            $values,
        );

        if ($this->relationLoaded('syncTracking')) {
            $this->syncTracking->refresh();
        }

        return $return;
    }

    /**
     * Overwrite this models sync metadata with the given metadata.
     */
    public function setSyncMetadata(array $metadata, ?string $source = null): SyncTrackedEntity
    {
        EmptySourceException::throwIfDisallowed($source);

        throw_if(
            $this->relationLoaded('syncTracking') && $this->syncTracking->isDirty(),
            'Please save your syncTracking model before setting meta data to avoid data loss'
        );
        $return = $this->syncTracking()->updateOrCreate(
            ['trackable_type' => get_class($this), 'trackable_id' => $this->getKey(), 'source' => $source],
            [
                'metadata' => $metadata,
            ]
        );

        if ($this->relationLoaded('syncTracking')) {
            $this->syncTracking->refresh();
        }

        return $return;
    }

    /**
     * Update this models sync metadata with the given fields. Don't change the other fields.
     */
    public function mergeSyncMetadata(array $metadata, ?string $source = null): SyncTrackedEntity
    {
        EmptySourceException::throwIfDisallowed($source);

        // Read the existing metadata from the row matching the given source,
        // not from the ordered syncTracking relation — that resolves to the
        // most recently synced row regardless of source, which would merge
        // another source's metadata into this one.
        $existing = $this->syncTracking()
            ->where('source', $source)
            ->value('metadata');

        return $this->setSyncMetadata(
            [
                ...($existing ?? []),
                ...$metadata,
            ],
            $source
        );
    }

    /**
     * Check if this model has been synced.
     */
    public function isSynced(): bool
    {
        return $this->syncTracking && $this->syncTracking->synced_at !== null;
    }

    /**
     * Get the external ID for this model.
     */
    public function getExternalId(): ?string
    {
        return $this->syncTracking ? $this->syncTracking->external_id : null;
    }

    /**
     * Get the external ID for this model from a specific source.
     */
    public function getExternalIdFromSource(string $source): ?string
    {
        return $this->syncTracking()
            ->where('source', $source)
            ->value('external_id');
    }

    /**
     * Get the sync source for this model.
     */
    public function getSyncSource(): ?string
    {
        return $this->syncTracking ? $this->syncTracking->source : null;
    }

    /**
     * Get the sync metadata for this model.
     */
    public function getSyncMetadata(): ?array
    {
        return $this->syncTracking ? $this->syncTracking->metadata : null;
    }
}
