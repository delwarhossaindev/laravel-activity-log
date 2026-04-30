<div align="center">

# 🪵 Laravel Activity Log

**Track everything. Miss nothing.**

Simple, elegant activity logging for Laravel — automatically records who did what, when, and how things changed.

[![Latest Version](https://img.shields.io/packagist/v/delwarhossaindev/laravel-activity-log?style=flat-square&color=blue&label=version)](https://packagist.org/packages/delwarhossaindev/laravel-activity-log)
[![Laravel](https://img.shields.io/badge/Laravel-8%2B-red?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/github/license/delwarhossaindev/laravel-activity-log?style=flat-square&color=green)](LICENSE)

</div>

---

## 🔧 Requirements & Compatibility

| Laravel Version | PHP Version | Support |
|---|---|---|
| Laravel **8.x** | PHP 7.4 / 8.0 | ✅ |
| Laravel **9.x** | PHP 8.0 / 8.1 | ✅ |
| Laravel **10.x** | PHP 8.1 / 8.2 | ✅ |
| Laravel **11.x** | PHP 8.2 / 8.3 | ✅ |
| Laravel **12.x** | PHP 8.2 / 8.3 / 8.4 | ✅ |
| Laravel 7.x and below | — | ❌ Not supported |

---

## ✨ What it does

| Feature | Description |
|---|---|
| 🤖 **Auto Logging** | Attach a trait — every create/update/delete is captured |
| ✍️ **Manual Logging** | Log anything with a one-liner helper |
| 🌐 **API Middleware** | Auto-log every HTTP request with method, URL, IP & status |
| 🔍 **Old / New Values** | See exactly what changed before and after |
| 📁 **Log Channels** | Separate logs by name (e.g. `payments`, `api`, `admin`) |
| 🔎 **Powerful Queries** | Filter by user, model, channel, date, and more |

---

## 📦 Installation

**Step 1 — Install the package**

```bash
composer require delwarhossaindev/laravel-activity-log
```

**Step 2 — Publish config & migration**

```bash
php artisan vendor:publish --tag=activitylog-config
php artisan vendor:publish --tag=activitylog-migrations
```

**Step 3 — Run the migration**

```bash
php artisan migrate
```

That's it. You're ready to log. ✅

---

## 🚀 Usage

### 1. Manual Logging

Use the `activity()` helper anywhere in your code.

```php
// Simplest form
activity()->log('Invoice was exported');

// Log what happened to a model
activity()
    ->performedOn($invoice)
    ->log('created');

// Log with a specific user as the cause
activity()
    ->performedOn($invoice)
    ->causedBy($user)
    ->log('approved');

// Log with extra data (any key/value)
activity()
    ->performedOn($order)
    ->withProperty('note', 'Flagged for review')
    ->log('flagged');

// Log to a named channel
activity('payments')
    ->performedOn($invoice)
    ->log('paid');
```

---

### 2. Auto Logging via Trait

Add `LogsActivity` to any Eloquent model. Done — every `created`, `updated`, and `deleted` event is automatically logged with the old and new values.

```php
use Delwarhossaindev\ActivityLog\Traits\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'body', 'status'];
}
```

#### Track only specific fields

```php
class Post extends Model
{
    use LogsActivity;

    // Only these fields will be tracked
    protected $logAttributes = ['title', 'status'];
}
```

#### Track only specific events

```php
class Post extends Model
{
    use LogsActivity;

    // Skip 'updated' — only log create and delete
    protected static $recordEvents = ['created', 'deleted'];
}
```

#### Customize the log description

```php
public function getActivityDescription(string $event): string
{
    return match($event) {
        'created' => 'Post was published',
        'updated' => 'Post was edited',
        'deleted' => 'Post was removed',
        default   => $event,
    };
}
```

---

### 3. API Middleware

Automatically log every API request — no extra code needed in controllers.

```php
// routes/api.php

// Protect an entire group
Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
    Route::apiResource('posts', PostController::class);
    Route::apiResource('invoices', InvoiceController::class);
});

// A single route
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['auth:sanctum', 'log.activity']);

// Use a custom channel name
Route::middleware(['auth:sanctum', 'log.activity:orders'])->group(function () {
    Route::apiResource('orders', OrderController::class);
});
```

Each request is stored with:

```json
{
    "method": "POST",
    "url": "https://yourapp.test/api/invoices",
    "ip": "127.0.0.1",
    "status": 201,
    "user_agent": "Mozilla/5.0 ..."
}
```

#### Only log write requests (skip GET)

```php
// config/activitylog.php
'api' => [
    'skip_methods' => ['GET', 'HEAD'],
],
```

#### Log the request body (great for payment/order APIs)

```php
'api' => [
    'log_request_body' => true,
    'hide_fields'      => ['password', 'token', 'card_number'], // masked as ***
],
```

#### Query API logs

```php
use Delwarhossaindev\ActivityLog\Activity;

Activity::inLog('api')->latest()->paginate(20);

// By user
Activity::inLog('api')->causedBy($user)->latest()->get();

// By HTTP method
Activity::inLog('api')
    ->where('description', 'like', 'POST %')
    ->latest()
    ->get();
```

---

### 4. Facade

```php
use Delwarhossaindev\ActivityLog\Facades\ActivityLog;

ActivityLog::on($post)->log('pinned');
```

---

### 5. Reading Logs

```php
use Delwarhossaindev\ActivityLog\Activity;

// All logs
Activity::latest()->get();

// Logs for a specific model
Activity::forSubject($post)->latest()->get();

// Logs caused by a specific user
Activity::causedBy($user)->latest()->get();

// Logs in a named channel
Activity::inLog('payments')->latest()->get();

// Via model relation (requires LogsActivity trait)
$post->activities;
```

#### Access old/new values

```php
$log = Activity::find(1);

$log->old;     // ['title' => 'Old Title']
$log->new;     // ['title' => 'New Title']
$log->changes; // ['old' => [...], 'new' => [...]]
```

---

### 6. Display in Blade (Admin Panel)

```blade
@foreach($activities as $activity)
    <tr>
        <td>{{ $activity->created_at->diffForHumans() }}</td>
        <td>{{ $activity->causer?->name ?? 'System' }}</td>
        <td>{{ class_basename($activity->subject_type) }}</td>
        <td>{{ ucfirst($activity->description) }}</td>
        <td>
            @if($activity->old)
                <span class="text-danger">{{ json_encode($activity->old) }}</span>
                →
                <span class="text-success">{{ json_encode($activity->new) }}</span>
            @endif
        </td>
    </tr>
@endforeach
```

---

## ⚙️ Configuration

```php
// config/activitylog.php

return [
    'default_log_name'         => 'default',
    'default_auth_driver'      => null,   // null = use default guard
    'activity_model'           => \Delwarhossaindev\ActivityLog\Activity::class,
    'table_name'               => 'activity_log',
    'database_connection'      => env('ACTIVITY_LOG_DB_CONNECTION', null),
    'log_attributes_on_update' => true,
];
```

---

## 🗄️ Database Schema

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `log_name` | string | Log channel (e.g. `default`, `api`, `payments`) |
| `description` | text | What happened (`created`, `updated`, etc.) |
| `subject_type` | string\|null | The model class that was affected |
| `subject_id` | bigint\|null | The ID of the affected model |
| `causer_type` | string\|null | Who caused it (usually your `User` model) |
| `causer_id` | bigint\|null | The ID of the user |
| `properties` | json\|null | Old/new attribute values |
| `created_at` | timestamp | When it happened |

---

## 📄 License

MIT © [Delwar Hossain](https://github.com/delwarhossaindev)
