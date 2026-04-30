<?php

namespace Delwarhossaindev\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * ActivityLogger — একটি activity log entry তৈরি ও সংরক্ষণ করার class।
 *
 * এই class টি fluent (chain করা যায়) পদ্ধতিতে কাজ করে।
 * একে একে তথ্য সেট করো, তারপর log() দিয়ে database-এ save করো।
 *
 * উদাহরণ:
 *   activity()
 *       ->performedOn($invoice)                        // কোন model-এ কাজ হয়েছে
 *       ->causedBy($user)                              // কে করেছে
 *       ->withProperty('note', 'Admin panel থেকে অনুমোদন')
 *       ->log('approved');                             // database-এ save করো
 *
 * log() call করার পরে সব তথ্য reset হয়ে যায়, তাই instance পুনর্ব্যবহার করা যায়।
 */
class ActivityLogger
{
    // log channel-এর নাম (যেমন: 'default', 'payments', 'api')
    protected string $logName;

    // যে model-এ কাজ হয়েছে (ঐচ্ছিক)
    protected ?Model $subject = null;

    // যে model কাজটি করেছে — দেওয়া না হলে logged-in user নেওয়া হবে
    protected ?Model $causer = null;

    // log-এর সাথে সংরক্ষণ করার অতিরিক্ত তথ্য (যেমন: old/new মান, নোট)
    protected array $properties = [];

    public function __construct()
    {
        // config থেকে default channel নাম নেওয়া হয়
        $this->logName = config('activitylog.default_log_name', 'default');
    }

    // -------------------------------------------------------------------------
    // Builder method — log() এর আগে এগুলো chain করো
    // -------------------------------------------------------------------------

    /**
     * log channel-এর নাম সেট করো।
     *
     * ব্যবহার:  activity()->useLog('payments')->log('paid');
     */
    public function useLog(string $logName): self
    {
        $this->logName = $logName;
        return $this;
    }

    /**
     * যে model-এ কাজ হয়েছে সেটি সেট করো (subject)।
     *
     * ব্যবহার:  activity()->performedOn($post)->log('created');
     */
    public function performedOn(Model $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * performedOn() এর সংক্ষিপ্ত রূপ।
     *
     * ব্যবহার:  ActivityLog::on($post)->log('pinned');
     */
    public function on(Model $subject): self
    {
        return $this->performedOn($subject);
    }

    /**
     * কে কাজটি করেছে সেটি সেট করো (causer)।
     * না দিলে স্বয়ংক্রিয়ভাবে logged-in user ব্যবহার হবে।
     *
     * ব্যবহার:  activity()->causedBy($adminUser)->log('deleted');
     */
    public function causedBy($causer): self
    {
        if ($causer instanceof Model) {
            $this->causer = $causer;
        }
        return $this;
    }

    /**
     * causedBy() এর সংক্ষিপ্ত রূপ।
     */
    public function by($causer): self
    {
        return $this->causedBy($causer);
    }

    /**
     * একসাথে অনেক property সেট করো।
     *
     * ব্যবহার:  activity()->withProperties(['old' => [...], 'new' => [...]])->log('updated');
     */
    public function withProperties(array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * একটি নির্দিষ্ট key-value property যোগ করো।
     *
     * ব্যবহার:  activity()->withProperty('note', 'পর্যালোচনার জন্য চিহ্নিত')->log('flagged');
     */
    public function withProperty(string $key, $value): self
    {
        $this->properties[$key] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Save করো
    // -------------------------------------------------------------------------

    /**
     * activity log entry database-এ save করো।
     *
     * এটি সবসময় chain-এর শেষ call।
     * save সফল হলে Activity model ফেরত দেয়, description খালি হলে null।
     *
     * ব্যবহার:  activity()->performedOn($post)->log('published');
     */
    public function log(string $description): ?Activity
    {
        // description খালি হলে কিছু save করা হবে না
        if (empty($description)) {
            return null;
        }

        // config-এ custom Activity model দেওয়া থাকলে সেটি ব্যবহার করো
        $activityModel = config('activitylog.activity_model', Activity::class);

        /** @var Activity $activity */
        $activity = new $activityModel();
        $activity->log_name    = $this->logName;
        $activity->description = $description;

        // properties খালি হলে null রাখো
        $activity->properties = $this->properties ?: null;

        // subject সেট থাকলে polymorphic relation-এ যুক্ত করো
        if ($this->subject) {
            $activity->subject()->associate($this->subject);
        }

        // causer সেট না থাকলে logged-in user খোঁজো
        $causer = $this->causer ?? $this->resolveAuthCauser();
        if ($causer) {
            $activity->causer()->associate($causer);
        }

        $activity->save();

        // পরবর্তী log-এর জন্য সব তথ্য পরিষ্কার করো
        $this->reset();

        return $activity;
    }

    // -------------------------------------------------------------------------
    // Internal helper
    // -------------------------------------------------------------------------

    /**
     * বর্তমানে authenticated user খোঁজার চেষ্টা করো।
     * কোনো user না থাকলে বা CLI-তে চললে null ফেরত দেয়।
     */
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

    /**
     * সব builder state পরিষ্কার করো যাতে পরবর্তী log() call fresh শুরু হয়।
     */
    protected function reset(): void
    {
        $this->logName    = config('activitylog.default_log_name', 'default');
        $this->subject    = null;
        $this->causer     = null;
        $this->properties = [];
    }
}
