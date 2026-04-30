<?php

use Delwarhossaindev\ActivityLog\ActivityLogger;

if (!function_exists('activity')) {
    /**
     * Return a fresh ActivityLogger instance, ready to build a log entry.
     *
     * This global helper is the easiest way to log an activity anywhere
     * in your application — controllers, jobs, services, listeners, etc.
     *
     * Basic usage:
     *   activity()->log('Something happened');
     *
     * With a subject model:
     *   activity()->performedOn($post)->log('published');
     *
     * With a named channel (stored under 'payments' instead of 'default'):
     *   activity('payments')->performedOn($invoice)->log('paid');
     *
     * @param  string|null  $logName  Optional channel name. If omitted, the
     *                                default from config('activitylog.default_log_name')
     *                                is used.
     */
    function activity(string $logName = null): ActivityLogger
    {
        /** @var ActivityLogger $logger */
        $logger = app(ActivityLogger::class);

        if ($logName !== null) {
            $logger->useLog($logName);
        }

        return $logger;
    }
}
