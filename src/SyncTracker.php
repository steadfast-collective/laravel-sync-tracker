<?php

namespace WizardingCode\FlowNetwork\SyncTracker;

use Illuminate\Database\Eloquent\Model;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;

class SyncTracker
{
    /**
     * Track the sync status for a model.
     */
    public function track(Model $model, array $attributes = []): SyncTrackedEntity
    {
        $entity = SyncTrackedEntity::for($model, $attributes['source'] ?? null);

        // for() already resolved the source; don't let a null in the
        // attributes overwrite it.
        unset($attributes['source']);

        return $entity
            ->fill(array_merge(['synced_at' => now()], $attributes))
            ->saveTracked();
    }

    /**
     * Mark a model as synced. Only overwrites the external ID and metadata
     * when new values are given. Fires an EntitySynced event.
     */
    public function markAsSynced(
        Model $model,
        ?string $externalId = null,
        ?string $source = null,
        ?array $metadata = null
    ): SyncTrackedEntity {
        return SyncTrackedEntity::for($model, $source)->markAsSynced($externalId, $metadata);
    }

    /**
     * Get the sync tracking information for a model.
     *
     * With a source, returns that source's row (or null when the model is
     * not tracked against it). Without one, returns the most recently synced
     * row across all sources — the never-synced lifecycle row sorts last, so
     * it only surfaces when no other row exists.
     */
    public function getSyncInfo(Model $model, ?string $source = null): ?SyncTrackedEntity
    {
        $query = SyncTrackedEntity::query()
            ->where('trackable_type', $model->getMorphClass())
            ->where('trackable_id', $model->getKey());

        return $source !== null
            ? $query->where('source', $source)->first()
            : $query->orderByMostRecentlySynced()->first();
    }

    /**
     * Check if a model has been synced — with the given source, or with any
     * source when none is given.
     */
    public function isSynced(Model $model, ?string $source = null): bool
    {
        return $this->getSyncInfo($model, $source)?->isSynced() ?? false;
    }

    /**
     * Find a model by its external ID and source.
     */
    public function findByExternalId(string $externalId, string $source, string $modelClass): ?Model
    {
        $tracking = SyncTrackedEntity::where([
            'external_id' => $externalId,
            'source' => $source,
            'trackable_type' => (new $modelClass)->getMorphClass(),
        ])
            ->whereHas('trackable')
            ->first();

        return $tracking?->trackable;
    }
}
