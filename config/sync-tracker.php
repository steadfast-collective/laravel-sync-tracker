<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sync Tracker Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the sync tracker package.
    |
    */

    // The table name used to store sync tracking information
    'table_name' => 'sync_tracked_entities',

    // Whether a tracking call may omit the sync source. It's recommended you disable
    // this when working with multiple-sources to enable strict-mode and avoid
    // accidentally using 'default'
    // source. An empty source string always throws, and automatic lifecycle
    // tracking (created/updated/deleted) is always exempt.
    'allow_empty_source' => true,

    // Default tracking options
    'default_tracking' => [
        // Whether to track creation timestamps by default
        'track_created' => true,

        // Whether to track update timestamps by default
        'track_updated' => true,

        // Whether to track deletion timestamps by default
        'track_deleted' => true,
    ],

    // Custom tracking models configuration
    'models' => [
        // Example:
        // App\Models\User::class => [
        //     'track_created' => true,
        //     'track_updated' => false,
        //     'track_deleted' => true,
        // ],
    ],
];
