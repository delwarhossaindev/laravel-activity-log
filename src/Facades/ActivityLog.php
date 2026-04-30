<?php

namespace Delwarhossaindev\ActivityLog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * ActivityLog — Laravel Facade for ActivityLogger.
 *
 * A Facade is a shortcut that lets you call methods on a class using a
 * clean static syntax, without needing to inject or instantiate it manually.
 *
 * Under the hood, every call on this Facade is forwarded to the
 * ActivityLogger singleton that is bound in the service container.
 *
 * Usage:
 *   use Delwarhossaindev\ActivityLog\Facades\ActivityLog;
 *
 *   ActivityLog::on($post)->log('pinned');
 *
 *   ActivityLog::performedOn($invoice)
 *       ->causedBy($user)
 *       ->log('approved');
 *
 * The activity() helper function does the same thing and is usually
 * more convenient for quick one-liners.
 *
 * Available methods (all proxy to ActivityLogger):
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger useLog(string $logName)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger performedOn(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger on(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger causedBy($causer)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger by($causer)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger withProperties(array $properties)
 * @method static \Delwarhossaindev\ActivityLog\ActivityLogger withProperty(string $key, $value)
 * @method static \Delwarhossaindev\ActivityLog\Activity|null log(string $description)
 *
 * @see \Delwarhossaindev\ActivityLog\ActivityLogger
 */
class ActivityLog extends Facade
{
    /**
     * Tell Laravel which container binding this Facade points to.
     * Laravel will resolve ActivityLogger from the container on every static call.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Delwarhossaindev\ActivityLog\ActivityLogger::class;
    }
}
