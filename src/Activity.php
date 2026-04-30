<?php

namespace Delwarhossaindev\ActivityLog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
    ];

    public function getTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    public function getConnectionName(): ?string
    {
        return config('activitylog.database_connection') ?? parent::getConnectionName();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get old attribute values (before update/delete).
     */
    public function getOldAttribute(): array
    {
        return $this->properties['old'] ?? [];
    }

    /**
     * Get new attribute values (after create/update).
     */
    public function getNewAttribute(): array
    {
        return $this->properties['new'] ?? [];
    }

    /**
     * Get both old and new as a changes array.
     */
    public function getChangesAttribute(): array
    {
        return array_filter([
            'old' => $this->old,
            'new' => $this->new,
        ]);
    }

    // ----- Scopes -----

    public function scopeInLog(Builder $query, string ...$logNames): Builder
    {
        return $query->whereIn('log_name', $logNames);
    }

    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeDescription(Builder $query, string $description): Builder
    {
        return $query->where('description', $description);
    }
}
