<?php

use Delwarhossaindev\ActivityLog\ActivityLogger;

if (!function_exists('activity')) {
    /**
     * Get a new ActivityLogger instance.
     *
     * Usage:
     *   activity()->log('Something happened');
     *   activity('orders')->performedOn($order)->log('created');
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
