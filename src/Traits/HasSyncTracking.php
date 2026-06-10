<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use WizardingCode\FlowNetwork\SyncTracker\Exceptions\EmptySourceException;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;

trait HasSyncTracking
{
    use DeprecatedSyncTrackerMethods;

    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootHasSyncTracking()
    {
        // Lifecycle events are recorded on their own row under the
        // '_lifecycle' sentinel source. They must never share a row with a
        // real source: stamping created/updated/deleted onto a source row
        // would corrupt that source's record of its last sync.
        static::created(function ($model) {
            if (config('sync-tracker.default_tracking.track_created', true)) {
                $model->syncTrackers()->updateOrCreate(
                    ['source' => SyncTrackedEntity::LIFECYCLE_SOURCE],
                    ['created_at' => now()]
                );
            }
        });

        static::updated(function ($model) {
            if (config('sync-tracker.default_tracking.track_updated', true)) {
                $model->syncTrackers()->updateOrCreate(
                    ['source' => SyncTrackedEntity::LIFECYCLE_SOURCE],
                    ['updated_at' => now()]
                );
            }
        });

        static::deleted(function ($model) {
            if (config('sync-tracker.default_tracking.track_deleted', true)) {
                $model->syncTrackers()->updateOrCreate(
                    ['source' => SyncTrackedEntity::LIFECYCLE_SOURCE],
                    ['deleted_at' => now()]
                );
            }
        });
    }

    /**
     * Get the sync tracking entity for the given source, creating an unsaved
     * one when this model has not been tracked against that source yet. All
     * sync reads and writes live on the returned entity.
     *
     * An omitted source resolves to the 'default' source (if the
     * allow_empty_source config option permits omitting it).
     */
    public function syncData(?string $source = null): SyncTrackedEntity
    {
        return SyncTrackedEntity::for($this, $source);
    }

    public static function findByExternalId(string $externalId, ?string $source = null)
    {
        EmptySourceException::throwIfDisallowed($source);

        // Sourceless rows live on the 'default' source.
        $source ??= SyncTrackedEntity::DEFAULT_SOURCE;

        $tracking = SyncTrackedEntity::where([
            'external_id' => $externalId,
            'source' => $source,
            'trackable_type' => static::query()->getModel()->getMorphClass(),
        ])
            ->whereHas('trackable')
            ->first();

        if (! $tracking) {
            return null;
        }

        return $tracking->trackable;
    }

    /**
     * Get all sync tracking entries for this model, including the lifecycle
     * row (filter it out with the withoutLifecycle scope).
     *
     * @return MorphMany<SyncTrackedEntity, $this>
     */
    public function syncTrackers(): MorphMany
    {
        return $this->morphMany(SyncTrackedEntity::class, 'trackable');
    }
}
