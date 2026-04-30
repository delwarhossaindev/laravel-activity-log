<?php

namespace Delwarhossaindev\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    protected string $logName;
    protected ?Model $subject  = null;
    protected ?Model $causer   = null;
    protected array  $properties = [];

    public function __construct()
    {
        $this->logName = config('activitylog.default_log_name', 'default');
    }

    public function useLog(string $logName): self
    {
        $this->logName = $logName;
        return $this;
    }

    public function performedOn(Model $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /** Alias for performedOn() */
    public function on(Model $subject): self
    {
        return $this->performedOn($subject);
    }

    public function causedBy($causer): self
    {
        if ($causer instanceof Model) {
            $this->causer = $causer;
        }
        return $this;
    }

    /** Alias for causedBy() */
    public function by($causer): self
    {
        return $this->causedBy($causer);
    }

    public function withProperties(array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    public function withProperty(string $key, $value): self
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function log(string $description): ?Activity
    {
        if (empty($description)) {
            return null;
        }

        $activityModel = config('activitylog.activity_model', Activity::class);

        /** @var Activity $activity */
        $activity = new $activityModel();
        $activity->log_name   = $this->logName;
        $activity->description = $description;
        $activity->properties  = $this->properties ?: null;

        if ($this->subject) {
            $activity->subject()->associate($this->subject);
        }

        $causer = $this->causer ?? $this->resolveAuthCauser();
        if ($causer) {
            $activity->causer()->associate($causer);
        }

        $activity->save();

        $this->reset();

        return $activity;
    }

    protected function resolveAuthCauser(): ?Model
    {
        $guard = config('activitylog.default_auth_driver');

        try {
            $user = Auth::guard($guard)->user();
            return $user instanceof Model ? $user : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function reset(): void
    {
        $this->logName    = config('activitylog.default_log_name', 'default');
        $this->subject    = null;
        $this->causer     = null;
        $this->properties = [];
    }
}
