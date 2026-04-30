<?php

namespace Delwarhossaindev\ActivityLog\Facades;

use Illuminate\Support\Facades\Facade;

/**
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
    protected static function getFacadeAccessor(): string
    {
        return \Delwarhossaindev\ActivityLog\ActivityLogger::class;
    }
}
