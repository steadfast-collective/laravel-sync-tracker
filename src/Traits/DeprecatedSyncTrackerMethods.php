<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use WizardingCode\FlowNetwork\SyncTracker\Models\SyncTrackedEntity;

/**
 * The pre-syncData() sync tracking API, kept as thin delegates for backwards
 * compatibility. See UPGRADE.md for the mapping to the replacement API.
 */
trait DeprecatedSyncTrackerMethods
{
    /**
     * Get the sync tracking information for this model.
     *
     * A model may be tracked against several sources; this resolves to the
     * most recently synced row regardless of source (the lifecycle row sorts
     * last because its synced_at is null), which is ambiguous once more than
     * one source is in play.
     *
     * @deprecated Ambiguous under multi-source. Use syncData($source) or syncTrackers().
     *
     * @return MorphOne<SyncTrackedEntity, $this>
     */
    public function syncTracking(): MorphOne
    {
        return $this->morphOne(SyncTrackedEntity::class, 'trackable')
            ->orderByMostRecentlySynced();
    }

    /**
     * Mark this model as synced.
     *
     * @deprecated Use syncData($source)->markAsSynced($externalId, $metadata).
     */
    public function markAsSynced(?string $externalId = null, ?string $source = null, ?array $metadata = null): SyncTrackedEntity
    {
        return $this->syncData($source)->markAsSynced($externalId, $metadata);
    }

    /**
     * Overwrite this models sync metadata with the given metadata.
     *
     * @deprecated Use syncData($source)->setSyncMetadata($metadata).
     */
    public function setSyncMetadata(array $metadata, ?string $source = null): SyncTrackedEntity
    {
        return $this->syncData($source)->setSyncMetadata($metadata);
    }

    /**
     * Update this models sync metadata with the given fields. Don't change the other fields.
     *
     * @deprecated Use syncData($source)->mergeSyncMetadata($metadata).
     */
    public function mergeSyncMetadata(array $metadata, ?string $source = null): SyncTrackedEntity
    {
        return $this->syncData($source)->mergeSyncMetadata($metadata);
    }

    /**
     * Check if this model has been synced with any source.
     *
     * @deprecated Ambiguous under multi-source. Use syncData($source)->isSynced().
     */
    public function isSynced(): bool
    {
        return $this->syncTracking && $this->syncTracking->synced_at !== null;
    }

    /**
     * Get the external ID for this model from the most recently synced source.
     *
     * @deprecated Ambiguous under multi-source. Use syncData($source)->external_id.
     */
    public function getExternalId(): ?string
    {
        return $this->syncTracking ? $this->syncTracking->external_id : null;
    }

    /**
     * Get the external ID for this model from a specific source.
     *
     * @deprecated Use syncData($source)->external_id.
     */
    public function getExternalIdFromSource(string $source): ?string
    {
        return $this->syncData($source)->external_id;
    }

    /**
     * Get the most recently synced source for this model.
     *
     * @deprecated Ambiguous under multi-source — returns the '_lifecycle'
     * sentinel for models that were never synced. Inspect syncTrackers() instead.
     */
    public function getSyncSource(): ?string
    {
        return $this->syncTracking ? $this->syncTracking->source : null;
    }

    /**
     * Get the sync metadata for this model from the most recently synced source.
     *
     * @deprecated Ambiguous under multi-source. Use syncData($source)->metadata.
     */
    public function getSyncMetadata(): ?array
    {
        return $this->syncTracking ? $this->syncTracking->metadata : null;
    }
}
