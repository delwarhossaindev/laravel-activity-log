<?php

namespace Delwarhossaindev\ActivityLog\Traits;

use Delwarhossaindev\ActivityLog\Activity;
use Delwarhossaindev\ActivityLog\ActivityLogger;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        foreach (static::getRecordedEvents() as $event) {
            static::$event(function (self $model) use ($event) {
                if (!$model->shouldLogActivity($event)) {
                    return;
                }

                $properties = $model->buildActivityProperties($event);

                // Skip if nothing changed on update
                if ($event === 'updated' && empty($properties)) {
                    return;
                }

                /** @var ActivityLogger $logger */
                $logger = app(ActivityLogger::class);

                $logger
                    ->performedOn($model)
                    ->withProperties($properties)
                    ->useLog($model->getActivityLogName())
                    ->log($model->getActivityDescription($event));
            });
        }
    }

    /**
     * Events to record. Override in model to customize.
     * Example: protected static $recordEvents = ['created', 'deleted'];
     */
    public static function getRecordedEvents(): array
    {
        return property_exists(static::class, 'recordEvents')
            ? static::$recordEvents
            : ['created', 'updated', 'deleted'];
    }

    /**
     * Attributes to track. Defaults to $fillable.
     * Override: protected $logAttributes = ['name', 'email'];
     */
    public function getTrackedAttributes(): array
    {
        if (property_exists($this, 'logAttributes') && !empty($this->logAttributes)) {
            return $this->logAttributes;
        }

        return !empty($this->fillable) ? $this->fillable : array_keys($this->getAttributes());
    }

    /**
     * Override to conditionally skip logging.
     */
    public function shouldLogActivity(string $event): bool
    {
        return true;
    }

    /**
     * Override to customize the log name per model.
     */
    public function getActivityLogName(): string
    {
        return property_exists($this, 'logName')
            ? $this->logName
            : config('activitylog.default_log_name', 'default');
    }

    /**
     * Override to customize the description.
     */
    public function getActivityDescription(string $event): string
    {
        return $event;
    }

    protected function buildActivityProperties(string $event): array
    {
        $tracked = $this->getTrackedAttributes();

        if ($event === 'created') {
            return [
                'new' => $this->filterAttributes($this->getAttributes(), $tracked),
            ];
        }

        if ($event === 'updated') {
            if (!config('activitylog.log_attributes_on_update', true)) {
                return [];
            }

            $dirty   = array_intersect_key($this->getDirty(), array_flip($tracked));
            if (empty($dirty)) {
                return [];
            }

            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $this->getOriginal($key);
            }

            return ['old' => $old, 'new' => $dirty];
        }

        if ($event === 'deleted') {
            return [
                'old' => $this->filterAttributes($this->getAttributes(), $tracked),
            ];
        }

        return [];
    }

    protected function filterAttributes(array $attributes, array $keys): array
    {
        return array_intersect_key($attributes, array_flip($keys));
    }

    /**
     * Get all activity logs for this model.
     */
    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
