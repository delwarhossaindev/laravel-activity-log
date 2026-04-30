# 📖 Code Documentation — Laravel Activity Log

> এই document-এ package-এর প্রতিটি file, class এবং method বাংলায় বিস্তারিত ব্যাখ্যা করা হয়েছে।

---

## 📁 Package-এর ফাইল কাঠামো

```
src/
├── Activity.php                        → Database model (একটি log row)
├── ActivityLogger.php                  → Log entry তৈরির builder class
├── ActivityLogServiceProvider.php      → Laravel-এ package load করার entry point
├── helpers.php                         → activity() global helper function
├── Facades/
│   └── ActivityLog.php                 → Static syntax-এর জন্য Facade
├── Http/
│   └── Middleware/
│       └── LogApiActivity.php          → API request auto-log করার middleware
└── Traits/
    └── LogsActivity.php                → Model-এ auto-log চালু করার Trait
```

---

## কীভাবে সব কিছু একসাথে কাজ করে

```
activity('payments')           ← helpers.php → ActivityLogger instance
    ->performedOn($invoice)    ┐
    ->causedBy($user)          ├── ActivityLogger.php → builder chain
    ->log('paid')              ┘
          │
          ▼
    Activity.php (Eloquent Model)
          │
          ▼
    activity_log table (database)
```

অথবা, Trait ব্যবহার করলে automatic flow:

```
$post->save()  →  bootLogsActivity()  →  ActivityLogger  →  activity_log table
                  (LogsActivity Trait)
```

---

## 1. `Activity.php` — Log Entry Model

**ফাইলের কাজ:** `activity_log` টেবিলের প্রতিটি row কে PHP object হিসেবে উপস্থাপন করে।

### Properties

| Property | কী করে |
|---|---|
| `$guarded = []` | সব column-এ mass-assignment অনুমতি দেয় |
| `$casts['properties']` | `properties` column-এর JSON কে স্বয়ংক্রিয়ভাবে PHP array-এ রূপান্তর করে |

### Methods

#### `getTable()`
```php
public function getTable(): string
```
`config/activitylog.php` থেকে টেবিলের নাম পড়ে। ডিফল্ট: `activity_log`।
কাস্টমাইজ করতে config-এ `table_name` পরিবর্তন করলেই হয়, code বদলাতে হয় না।

---

#### `getConnectionName()`
```php
public function getConnectionName(): ?string
```
Activity log আলাদা database-এ রাখতে চাইলে `config/activitylog.php`-এ `database_connection` সেট করো।
না দিলে app-এর default connection ব্যবহার হবে।

---

#### `subject()` — Polymorphic Relation
```php
public function subject(): MorphTo
```
যে model-এ কাজ হয়েছে তার সাথে সম্পর্ক।
Polymorphic মানে যেকোনো model (Post, Invoice, User যেকোনোটি) subject হতে পারে।

```php
$log->subject;  // Post, Invoice বা যেকোনো model instance ফেরত দেবে
```

---

#### `causer()` — Polymorphic Relation
```php
public function causer(): MorphTo
```
যে user/model কাজটি করেছে তার সাথে সম্পর্ক।

```php
$log->causer;       // User model instance
$log->causer->name; // "Delwar Hossain"
```

---

#### `getOldAttribute()` — Accessor
```php
public function getOldAttribute(): array
```
`$log->old` লিখলে এই method call হয়।
`properties` JSON-এর `old` key থেকে পরিবর্তনের আগের মান বের করে দেয়।

```php
$log->old;  // ['title' => 'পুরানো শিরোনাম']
```

---

#### `getNewAttribute()` — Accessor
```php
public function getNewAttribute(): array
```
`$log->new` লিখলে এই method call হয়।
পরিবর্তনের পরের মান বের করে দেয়।

```php
$log->new;  // ['title' => 'নতুন শিরোনাম']
```

---

#### `getChangesAttribute()` — Accessor
```php
public function getChangesAttribute(): array
```
`$log->changes` লিখলে old ও new একসাথে পাওয়া যায়।

```php
$log->changes;
// ['old' => ['title' => 'পুরানো'], 'new' => ['title' => 'নতুন']]
```

---

#### Query Scopes

Scope হলো reusable WHERE clause যা model query-তে চেইন করা যায়।

| Scope | SQL সমতুল্য | উদাহরণ |
|---|---|---|
| `scopeInLog($query, ...$names)` | `WHERE log_name IN (...)` | `Activity::inLog('api')->get()` |
| `scopeCausedBy($query, $model)` | `WHERE causer_type=? AND causer_id=?` | `Activity::causedBy($user)->get()` |
| `scopeForSubject($query, $model)` | `WHERE subject_type=? AND subject_id=?` | `Activity::forSubject($post)->get()` |
| `scopeDescription($query, $text)` | `WHERE description=?` | `Activity::description('created')->get()` |

---

## 2. `ActivityLogger.php` — Log Builder

**ফাইলের কাজ:** Fluent (chain করা যায়) পদ্ধতিতে একটি log entry তৈরি করে database-এ save করে।

### কীভাবে কাজ করে (Flow)

```
new ActivityLogger()
      │
      ├── useLog('payments')       → $logName সেট করো
      ├── performedOn($invoice)    → $subject সেট করো
      ├── causedBy($user)          → $causer সেট করো
      ├── withProperty('k', 'v')   → $properties-এ যোগ করো
      │
      └── log('paid')              → Activity row তৈরি করে save করো
                                     তারপর reset() দিয়ে পরিষ্কার করো
```

### Properties

| Property | Type | কাজ |
|---|---|---|
| `$logName` | `string` | কোন channel-এ log যাবে |
| `$subject` | `?Model` | কোন model-এ কাজ হয়েছে |
| `$causer` | `?Model` | কে করেছে |
| `$properties` | `array` | অতিরিক্ত তথ্য (old/new values, notes) |

### Methods

#### `useLog(string $logName)`
Log channel-এর নাম সেট করে। না দিলে config-এর `default_log_name` ব্যবহার হয়।

```php
activity()->useLog('payments')->log('paid');
// log_name = 'payments'
```

---

#### `performedOn(Model $subject)` / `on(Model $subject)`
যে model-এ কাজ হয়েছে সেটি সেট করে। `on()` হলো সংক্ষিপ্ত রূপ।

```php
activity()->performedOn($post)->log('deleted');
activity()->on($post)->log('deleted');  // একই কাজ
```

---

#### `causedBy($causer)` / `by($causer)`
কে করেছে সেটি সেট করে। না দিলে `resolveAuthCauser()` দিয়ে logged-in user খোঁজা হয়।

```php
activity()->causedBy($adminUser)->log('approved');
activity()->by($adminUser)->log('approved');  // একই কাজ
```

---

#### `withProperties(array $properties)` / `withProperty(string $key, $value)`

```php
// একটি property
activity()->withProperty('note', 'Admin approved')->log('approved');

// একসাথে অনেক
activity()->withProperties(['old' => [...], 'new' => [...]])->log('updated');
```

---

#### `log(string $description)` — সবচেয়ে গুরুত্বপূর্ণ Method

```php
public function log(string $description): ?Activity
```

সব চেইনের শেষে এটি call করলে:
1. `Activity` model তৈরি হয়
2. `log_name`, `description`, `properties` সেট হয়
3. `subject` এবং `causer` polymorphic relation-এ যুক্ত হয়
4. Database-এ `save()` হয়
5. `reset()` দিয়ে সব পরিষ্কার হয়

---

#### `resolveAuthCauser()` — Protected
```php
protected function resolveAuthCauser(): ?Model
```
`Auth::guard($guard)->user()` দিয়ে logged-in user বের করে।
কোনো user না থাকলে বা CLI-তে চললে `null` ফেরত দেয়।

---

#### `reset()` — Protected
`log()` call-এর পরে সব property পরিষ্কার করে।
এই কারণেই একই instance বারবার ব্যবহার করা যায়।

---

## 3. `Traits/LogsActivity.php` — Auto-Log Trait

**ফাইলের কাজ:** যেকোনো Eloquent model-এ এই trait যোগ করলে সেই model-এর create/update/delete স্বয়ংক্রিয়ভাবে log হয়।

### কীভাবে চালু করতে হয়

```php
class Post extends Model
{
    use LogsActivity;  // শুধু এটুকু যোগ করলেই হয়
}
```

### `bootLogsActivity()` — কীভাবে কাজ করে

Laravel trait-এ `boot{TraitName}()` নামের method স্বয়ংক্রিয়ভাবে চালায়।
এই method প্রতিটি event-এর জন্য একটি করে listener register করে:

```
bootLogsActivity() চলে
      │
      ├── static::created(callback)   ← Post create হলে
      ├── static::updated(callback)   ← Post update হলে
      └── static::deleted(callback)   ← Post delete হলে

প্রতিটি callback:
  shouldLogActivity() → false হলে skip
  buildActivityProperties() → old/new values বের করো
  ActivityLogger → log save করো
```

---

### Customization — Model-এ Override করার Options

#### ১. কোন field track করবে
```php
// শুধু এই দুটি field track হবে
protected $logAttributes = ['title', 'status'];
```
না দিলে `$fillable` অথবা সব attribute track হবে।

---

#### ২. কোন event log হবে
```php
// শুধু create আর delete, update skip
protected static $recordEvents = ['created', 'deleted'];
```

---

#### ৩. Log description কাস্টমাইজ
```php
public function getActivityDescription(string $event): string
{
    return match($event) {
        'created' => 'পোস্ট প্রকাশিত হয়েছে',
        'updated' => 'পোস্ট সম্পাদনা করা হয়েছে',
        'deleted' => 'পোস্ট মুছে ফেলা হয়েছে',
        default   => $event,
    };
}
```

---

#### ৪. Log channel কাস্টমাইজ
```php
protected $logName = 'posts';  // 'posts' channel-এ log যাবে
```

---

#### ৫. শর্ত দিয়ে logging বন্ধ রাখো
```php
public function shouldLogActivity(string $event): bool
{
    return !$this->is_system_action;  // system action হলে log করবে না
}
```

---

### `buildActivityProperties()` — কোন event-এ কী সংরক্ষণ হয়

| Event | Properties | ব্যাখ্যা |
|---|---|---|
| `created` | `['new' => [...]]` | নতুন row-এর সব tracked মান |
| `updated` | `['old' => [...], 'new' => [...]]` | শুধু পরিবর্তিত field-এর আগের ও পরের মান |
| `deleted` | `['old' => [...]]` | delete হওয়ার সময়কার সব tracked মান |

`updated` event-এ `getDirty()` ব্যবহার করা হয় — এটি শুধু পরিবর্তিত field গুলো দেয়।

---

## 4. `helpers.php` — Global Helper

**ফাইলের কাজ:** `activity()` নামে একটি global function তৈরি করে যা যেকোনো জায়গা থেকে ব্যবহার করা যায়।

```php
function activity(string $logName = null): ActivityLogger
```

`app(ActivityLogger::class)` দিয়ে service container থেকে `ActivityLogger` instance নেয়।
Channel name দিলে `useLog()` call করে দেয়।

```php
activity()            // default channel
activity('payments')  // payments channel
```

---

## 5. `Http/Middleware/LogApiActivity.php` — API Middleware

**ফাইলের কাজ:** `log.activity` middleware দিলে প্রতিটি HTTP request স্বয়ংক্রিয়ভাবে log হয়।

### Request-এর flow

```
HTTP Request আসে
      │
      ▼
handle() — $response = $next($request)  ← আগে request process হতে দাও
      │
      ▼
shouldLog() — এই request log করা উচিত?
      │
      ├── skip_methods-এ আছে? (যেমন GET) → না, skip করো
      ├── skip_statuses-এ আছে? (যেমন 404) → না, skip করো
      └── না → logRequest() call করো
                      │
                      ▼
              buildProperties() → method, url, ip, status, user_agent সংগ্রহ করো
                      │
                      ▼
              ActivityLogger → database-এ save করো
```

---

### `shouldLog()` — কখন log skip করবে

```php
// config/activitylog.php
'api' => [
    'skip_methods'  => ['GET', 'HEAD'],  // GET request log করবে না
    'skip_statuses' => [404, 500],       // এই status code-এ log করবে না
],
```

---

### `buildProperties()` — কী কী সংরক্ষণ হয়

| Key | মান | সবসময়? |
|---|---|---|
| `method` | GET, POST, PUT, DELETE | ✅ হ্যাঁ |
| `url` | সম্পূর্ণ URL | ✅ হ্যাঁ |
| `ip` | Client-এর IP | ✅ হ্যাঁ |
| `status` | HTTP status code (200, 201, 422...) | ✅ হ্যাঁ |
| `user_agent` | Browser/app নাম | ✅ হ্যাঁ |
| `request` | Request body | ⚙️ `log_request_body: true` হলে |
| `response` | Response body | ⚙️ `log_response_body: true` হলে |

---

### `sanitizeBody()` — Sensitive তথ্য লুকানো

`password`, `token` এর মতো field-এর মান `***` দিয়ে replace করে।

```php
// Input
['email' => 'user@test.com', 'password' => 'secret123']

// Output (database-এ যা যাবে)
['email' => 'user@test.com', 'password' => '***']
```

Config-এ কাস্টমাইজ করা যায়:
```php
'hide_fields' => ['password', 'token', 'card_number', 'cvv'],
```

---

## 6. `ActivityLogServiceProvider.php` — Service Provider

**ফাইলের কাজ:** Laravel-কে জানায় এই package কীভাবে load করতে হবে।
`composer.json`-এর `extra.laravel.providers` key দিয়ে Laravel স্বয়ংক্রিয়ভাবে এটি চেনে।

### `register()` — সবার আগে চলে

```php
public function register(): void
```

দুটি কাজ করে:

**১. Config merge করে:**
App `config/activitylog.php` publish না করলেও package-এর নিজস্ব default config কাজ করে।

**২. Singleton bind করে:**
```php
$this->app->singleton(ActivityLogger::class, function () {
    return new ActivityLogger();
});
```
Singleton মানে একটি request-এ মাত্র একটি `ActivityLogger` instance তৈরি হয়।
`reset()` method প্রতিটি `log()` call-এর পরে state পরিষ্কার করে, তাই এটি নিরাপদ।

---

### `boot()` — register() এর পরে চলে

দুটি কাজ করে:

**১. Middleware register করে:**
`log.activity` alias টি route file-এ ব্যবহার করা যায়।

**২. Publish করার সুবিধা দেয়:**
```bash
php artisan vendor:publish --tag=activitylog-config
php artisan vendor:publish --tag=activitylog-migrations
```

---

## 7. `Facades/ActivityLog.php` — Facade

**ফাইলের কাজ:** `ActivityLogger` class-কে static syntax-এ ব্যবহার করার সুবিধা দেয়।

### Facade কী?

Facade হলো একটি "দরজা" যা service container-এর ভেতরে থাকা object-কে static method-এর মতো call করতে দেয়।

```php
// Facade ছাড়া (Dependency Injection)
public function store(ActivityLogger $logger) {
    $logger->on($post)->log('created');
}

// Facade দিয়ে (যেকোনো জায়গায়)
ActivityLog::on($post)->log('created');
```

### `getFacadeAccessor()`
```php
protected static function getFacadeAccessor(): string
{
    return ActivityLogger::class;
}
```
Laravel-কে বলে — এই Facade-এর static call গুলো `ActivityLogger` instance-এ পাঠাও।

### `activity()` helper vs `ActivityLog` Facade

| | `activity()` helper | `ActivityLog` Facade |
|---|---|---|
| Style | `activity()->on($p)->log('x')` | `ActivityLog::on($p)->log('x')` |
| Import লাগে? | না | হ্যাঁ (`use ... Facades\ActivityLog`) |
| কোনটি ভালো? | Controller/Service-এ সহজ | যেকোনো context-এ explicit |

দুটিই একই `ActivityLogger` instance ব্যবহার করে — পার্থক্য শুধু syntax-এ।

---

## সংক্ষেপে — কোন file কখন কাজে লাগে

| কাজ | ব্যবহার করো |
|---|---|
| যেকোনো জায়গায় manually log করতে | `activity()` helper বা `ActivityLog` Facade |
| Model-এ auto log চালু করতে | `LogsActivity` Trait |
| API route-এ auto log চালু করতে | `log.activity` Middleware |
| Log পড়তে বা query করতে | `Activity` Model |
| Package কাস্টমাইজ করতে | `config/activitylog.php` |
