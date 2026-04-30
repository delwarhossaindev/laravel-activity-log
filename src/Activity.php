<?php

namespace Delwarhossaindev\ActivityLog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity — activity_log টেবিলের একটি row-এর Eloquent Model।
 *
 * অ্যাপে যখনই কিছু ঘটে — যেমন একটি Post তৈরি হয়, কোনো User আপডেট হয়,
 * বা একটি API call আসে — তখন সেই ঘটনার তথ্য এই model-এ একটি row হিসেবে সংরক্ষিত হয়।
 *
 * গুরুত্বপূর্ণ column গুলো:
 *   log_name    → কোন channel-এর log (যেমন: 'default', 'api', 'payments')
 *   description → কী হয়েছে (যেমন: 'created', 'updated', 'POST /api/orders')
 *   subject     → কোন model-এর উপর কাজটি হয়েছে (যেমন: Post, Invoice)
 *   causer      → কে করেছে (সাধারণত logged-in User)
 *   properties  → পরিবর্তনের আগের ও পরের মান JSON হিসেবে
 */
class Activity extends Model
{
    // সব column-এ mass-assignment অনুমতি দেওয়া হয়েছে
    protected $guarded = [];

    // 'properties' column-এর JSON ডেটা স্বয়ংক্রিয়ভাবে PHP array-এ রূপান্তরিত হবে
    protected $casts = [
        'properties' => 'array',
    ];

    // -------------------------------------------------------------------------
    // টেবিল ও Connection সংক্রান্ত
    // -------------------------------------------------------------------------

    /**
     * config থেকে টেবিলের নাম নেওয়া হয়, যাতে code না বদলেও নাম পরিবর্তন করা যায়।
     */
    public function getTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    /**
     * config থেকে database connection নেওয়া হয়।
     * activity log আলাদা database-এ রাখতে চাইলে এটি কাজে আসে।
     */
    public function getConnectionName(): ?string
    {
        return config('activitylog.database_connection') ?? parent::getConnectionName();
    }

    // -------------------------------------------------------------------------
    // Relationships — সম্পর্ক
    // -------------------------------------------------------------------------

    /**
     * যে model-এর উপর কাজটি হয়েছে (যেমন: Post, Invoice, User)।
     * Polymorphic relation, তাই যেকোনো model subject হতে পারে।
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * যে model কাজটি করেছে (সাধারণত logged-in User)।
     * Polymorphic relation, তাই যেকোনো model causer হতে পারে।
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Accessors — সংরক্ষিত মান পড়ার শর্টকাট
    // -------------------------------------------------------------------------

    /**
     * পরিবর্তনের আগের মান ফেরত দেয়।
     *
     * উদাহরণ:
     *   $log->old  // ['title' => 'পুরানো শিরোনাম', 'status' => 'draft']
     */
    public function getOldAttribute(): array
    {
        return $this->properties['old'] ?? [];
    }

    /**
     * পরিবর্তনের পরের মান ফেরত দেয়।
     *
     * উদাহরণ:
     *   $log->new  // ['title' => 'নতুন শিরোনাম', 'status' => 'published']
     */
    public function getNewAttribute(): array
    {
        return $this->properties['new'] ?? [];
    }

    /**
     * আগের ও পরের মান একসাথে একটি array হিসেবে ফেরত দেয়।
     * খালি key গুলো সরিয়ে পরিষ্কার রাখা হয়।
     *
     * উদাহরণ:
     *   $log->changes
     *   // ['old' => ['title' => 'পুরানো'], 'new' => ['title' => 'নতুন']]
     */
    public function getChangesAttribute(): array
    {
        return array_filter([
            'old' => $this->old,
            'new' => $this->new,
        ]);
    }

    // -------------------------------------------------------------------------
    // Query Scopes — log ফিল্টার করার জন্য পুনর্ব্যবহারযোগ্য WHERE clause
    // -------------------------------------------------------------------------

    /**
     * নির্দিষ্ট channel নাম দিয়ে log ফিল্টার করে।
     *
     * ব্যবহার:
     *   Activity::inLog('payments')->get();
     *   Activity::inLog('api', 'payments')->get();
     */
    public function scopeInLog(Builder $query, string ...$logNames): Builder
    {
        return $query->whereIn('log_name', $logNames);
    }

    /**
     * নির্দিষ্ট user বা model-এর করা log গুলো ফিল্টার করে।
     *
     * ব্যবহার:
     *   Activity::causedBy($user)->get();
     */
    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    /**
     * নির্দিষ্ট model instance সম্পর্কিত log গুলো ফিল্টার করে।
     *
     * ব্যবহার:
     *   Activity::forSubject($post)->get();
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * নির্দিষ্ট description দিয়ে log ফিল্টার করে।
     *
     * ব্যবহার:
     *   Activity::description('created')->get();
     */
    public function scopeDescription(Builder $query, string $description): Builder
    {
        return $query->where('description', $description);
    }
}
