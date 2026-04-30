<?php

return [

    /*
     * Default log name when none is specified.
     */
    'default_log_name' => 'default',

    /*
     * Auth guard to resolve the causer (logged-in user).
     * Set null to use the default guard.
     */
    'default_auth_driver' => null,

    /*
     * The model used to store activity. You can extend it if needed.
     */
    'activity_model' => \Delwarhossaindev\ActivityLog\Activity::class,

    /*
     * Database table name for activity logs.
     */
    'table_name' => 'activity_log',

    /*
     * Database connection. Set null to use the default connection.
     */
    'database_connection' => env('ACTIVITY_LOG_DB_CONNECTION', null),

    /*
     * If true, old/new attribute changes are automatically logged for updated events.
     */
    'log_attributes_on_update' => true,

    /*
     * API middleware settings (used by LogApiActivity middleware).
     */
    'api' => [

        // Log channel name for API requests
        'log_name' => 'api',

        // HTTP methods to skip (e.g. skip GET to only log writes)
        'skip_methods' => [],

        // HTTP status codes to skip (e.g. skip 401 noise)
        'skip_statuses' => [],

        // Log the incoming request body (POST/PUT data)
        'log_request_body' => false,

        // Log the outgoing response body
        'log_response_body' => false,

        // Fields to mask in request/response body
        'hide_fields' => ['password', 'password_confirmation', 'token', 'secret'],

    ],

];
