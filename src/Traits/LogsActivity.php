<?php

namespace Delwarhossaindev\ActivityLog\Traits;

use Delwarhossaindev\ActivityLog\Activity;
use Delwarhossaindev\ActivityLog\ActivityLogger;

/**
 * LogsActivity — Attach this trait to any Eloquent model to enable automatic logging.
 *
 * Once added, every create / update / delete on that model is automatically
 * recorded in the activity_log table — no extra code needed in controllers.
 *
 * Quick start:
 *   class Post extends Model
 *   {
 *       use LogsActivity;
 *   }
 *
 * Customisation hooks (all optional, override in your model):
 *   - $logAttributes       → which fields to track
 *   - $recordEvents        → which events to listen for
 *   - getActivityDescription() → custom log description text
 *   - getActivityLogName()     → which channel to log into
 *   - shouldLogActivity()      → conditionally skip logging
 */
trait LogsActivity
{
    /**
     * Boot the trait — this runs automatically when the model class is loaded.
     *
     * It registers a listener for each event we want to track (created, updated,
     * deleted). Laravel calls the matching static method on the model whenever
     * that event fires (e.g. static::created(...), static::updated(...)).
     */
    public static function bootLogsActivity(): void
    {
        foreach (static::getRecordedEvents() as $event) {

            // Register a callback for this event (e.g. 'created', 'updated', 'deleted')
            static::$event(function (self $model) use ($event) {

                // Allow the model to skip logging for a specific event/condition
                if (!$model->shouldLogActivity($event)) {
                    return;
                }

                // Gather old/new attribute values to store alongside the log
                $properties = $model->buildActivityProperties($event);

                // On updates, if nothing actually changed, skip to avoid empty log rows
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

    // -------------------------------------------------------------------------
    // Configuration methods — override any of these in your model
    // -------------------------------------------------------------------------

    /**
     * Which Eloquent events should trigger a log entry.
     *
     * Default: ['created', 'updated', 'deleted']
     *
     * To log only specific events, add this to your model:
     *   protected static $recordEvents = ['created', 'deleted'];
     */
    public static function getRecordedEvents(): array
    {
        return property_exists(static::class, 'recordEvents')
            ? static::$recordEvents
            : ['created', 'updated', 'deleted'];
    }

    /**
     * Which model attributes to track in old/new values.
     *
     * Priority:
     *   1. $logAttributes property  → use exactly those fields
     *   2. $fillable property       → use all fillable fields
     *   3. fallback                 → use all attributes on the model
     *
     * Example — track only title and status:
     *   protected $logAttributes = ['title', 'status'];
     */
    public function getTrackedAttributes(): array
    {
        if (property_exists($this, 'logAttributes') && !empty($this->logAttributes)) {
            return $this->logAttributes;
        }

        return !empty($this->fillable) ? $this->fillable : array_keys($this->getAttributes());
    }

    /**
     * Return false to skip logging for a particular event or condition.
     *
     * Example — skip logging for system-generated updates:
     *   public function shouldLogActivity(string $event): bool
     *   {
     *       return !$this->is_system_action;
     *   }
     */
    public function shouldLogActivity(string $event): bool
    {
        return true;
    }

    /**
     * The log channel name for this model's entries.
     *
     * To use a custom channel, add this to your model:
     *   protected $logName = 'posts';
     */
    public function getActivityLogName(): string
    {
        return property_exists($this, 'logName')
            ? $this->logName
            : config('activitylog.default_log_name', 'default');
    }

    /**
     * The description text stored in the log entry.
     *
     * Defaults to the event name ('created', 'updated', 'deleted').
     *
     * To customise, override this method in your model:
     *   public function getActivityDescription(string $event): string
     *   {
     *       return match($event) {
     *           'created' => 'Post was published',
     *           'updated' => 'Post was edited',
     *           'deleted' => 'Post was removed',
     *           default   => $event,
     *       };
     *   }
     */
    public function getActivityDescription(string $event): string
    {
        return $event;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the properties array to store alongside the log entry.
     *
     * created → stores the new attribute values
     * updated → stores only the fields that actually changed (old and new)
     * deleted → stores the attribute values at the time of deletion
     */
    protected function buildActivityProperties(string $event): array
    {
        $tracked = $this->getTrackedAttributes();

        if ($event === 'created') {
            // Store all tracked attributes as 'new' (there are no 'old' values yet)
            return [
                'new' => $this->filterAttributes($this->getAttributes(), $tracked),
            ];
        }

        if ($event === 'updated') {
            // If the config says not to track attribute changes, return nothing
            if (!config('activitylog.log_attributes_on_update', true)) {
                return [];
            }

            // getDirty() returns only the fields that were changed in this request
            $dirty = array_intersect_key($this->getDirty(), array_flip($tracked));

            // Nothing worth logging if no tracked field changed
            if (empty($dirty)) {
                return [];
            }

            // Collect the original (before-save) values for each changed field
            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $this->getOriginal($key);
            }

            return ['old' => $old, 'new' => $dirty];
        }

        if ($event === 'deleted') {
            // Store tracked attributes as 'old' (the row is about to disappear)
            return [
                'old' => $this->filterAttributes($this->getAttributes(), $tracked),
            ];
        }

        return [];
    }

    /**
     * Keep only the keys that appear in the $keys list.
     * Used to strip out attributes we are not supposed to track.
     */
    protected function filterAttributes(array $attributes, array $keys): array
    {
        return array_intersect_key($attributes, array_flip($keys));
    }

    // -------------------------------------------------------------------------
    // Relationship
    // -------------------------------------------------------------------------

    /**
     * Retrieve all activity log entries for this model instance.
     *
     * Usage:
     *   $post->activities;
     *   $post->activities()->latest()->paginate(10);
     */
    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
