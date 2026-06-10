<?php

namespace WizardingCode\FlowNetwork\SyncTracker\Exceptions;

use InvalidArgumentException;

class EmptySourceException extends InvalidArgumentException
{
    /**
     * Guard the source argument of the sync APIs.
     *
     * An empty source string is always rejected. An omitted (null) source is
     * rejected only when the sync-tracker.allow_empty_source config option
     * disallows it — when allowed, sourceless calls resolve to the 'default'
     * source.
     */
    public static function throwIfDisallowed(?string $source): void
    {
        if ($source === null && ! config('sync-tracker.allow_empty_source', true)) {
            throw new self(
                'No sync source was provided, but empty sources are disabled by the '
                .'sync-tracker.allow_empty_source config option. Pass a source, or set the option to true.'
            );
        }

        if ($source !== null && trim($source) === '') {
            throw new self('A sync source cannot be an empty string. Pass a non-empty source, or omit it.');
        }
    }
}
