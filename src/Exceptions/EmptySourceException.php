<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Exceptions;

use InvalidArgumentException;

class EmptySourceException extends InvalidArgumentException
{
    /**
     * Throw when no source is given while the sync-tracker.allow_empty_source
     * config option disallows empty sources.
     */
    public static function throwIfDisallowed(?string $source): void
    {
        if ($source === null && ! config('sync-tracker.allow_empty_source', true)) {
            throw new self(
                'No sync source was provided, but empty sources are disabled by the '
                .'sync-tracker.allow_empty_source config option. Pass a source, or set the option to true.'
            );
        }
    }
}
