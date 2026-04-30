# Laravel Activity Log

Simple and elegant activity logging for Laravel. Automatically track model events (created, updated, deleted) with old/new attribute values. Perfect for admin panels and audit trails.

## Installation

```bash
composer require delwarhossaindev/laravel-activity-log
```

Publish config and migration:

```bash
php artisan vendor:publish --tag=activitylog-config
php artisan vendor:publish --tag=activitylog-migrations
php artisan migrate
```

## Usage

### 1. Manual Logging (helper function)

```php
// Simple log
activity()->log('Invoice was exported');

// With a subject model
activity()
    ->performedOn($invoice)
    ->log('created');

// With custom causer
activity()
    ->performedOn($invoice)
    ->causedBy($user)
    ->log('approved');

// With extra data
activity()
    ->performedOn($order)
    ->withProperty('note', 'Flagged for review')
    ->log('flagged');

// Named log channel
activity('payments')
    ->performedOn($invoice)
    ->log('paid');
```

### 2. Auto Logging via Trait

Add the `LogsActivity` trait to any Eloquent model:

```php
use Delwarhossaindev\ActivityLog\Traits\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'body', 'status'];
}
```

Now `created`, `updated`, `deleted` events are automatically logged with old/new values.

#### Customize tracked attributes

```php
class Post extends Model
{
    use LogsActivity;

    // Only track these fields
    protected $logAttributes = ['title', 'status'];
}
```

#### Customize recorded events

```php
class Post extends Model
{
    use LogsActivity;

    // Only log create and delete, skip update
    protected static $recordEvents = ['created', 'deleted'];
}
```

#### Customize description

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

### 3. API Middleware (Auto-log all API requests)

Apply the `log.activity` middleware to any API route or group:

```php
// routes/api.php

// Entire API group
Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
    Route::apiResource('posts', PostController::class);
    Route::apiResource('invoices', InvoiceController::class);
});

// Single route
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['auth:sanctum', 'log.activity']);

// Custom log channel (stored as 'orders' instead of 'api')
Route::middleware(['auth:sanctum', 'log.activity:orders'])->group(function () {
    Route::apiResource('orders', OrderController::class);
});
```

Each request is logged with:

```json
{
    "method": "POST",
    "url": "https://yourapp.test/api/invoices",
    "ip": "127.0.0.1",
    "status": 201,
    "user_agent": "Mozilla/5.0 ..."
}
```

#### Skip GET requests (only log writes)

```php
// config/activitylog.php
'api' => [
    'skip_methods' => ['GET', 'HEAD'],
],
```

#### Also log request body (e.g. for payment/order APIs)

```php
'api' => [
    'log_request_body' => true,
    'hide_fields'      => ['password', 'token', 'card_number'], // these become ***
],
```

#### Read API logs

```php
use Delwarhossaindev\ActivityLog\Activity;

// All API activity
Activity::inLog('api')->latest()->paginate(20);

// API calls made by a specific user
Activity::inLog('api')->causedBy($user)->latest()->get();

// Filter by HTTP method
Activity::inLog('api')
    ->where('description', 'like', 'POST %')
    ->latest()
    ->get();
```

### 5. Facade

```php
use Delwarhossaindev\ActivityLog\Facades\ActivityLog;

ActivityLog::on($post)->log('pinned');
```

### 6. Reading Logs

```php
use Delwarhossaindev\ActivityLog\Activity;

// All logs
$logs = Activity::latest()->get();

// Logs for a specific model
$logs = Activity::forSubject($post)->latest()->get();

// Logs caused by a specific user
$logs = Activity::causedBy($user)->latest()->get();

// Logs in a named channel
$logs = Activity::inLog('payments')->latest()->get();

// Via model relation (requires LogsActivity trait)
$post->activities;
```

#### Accessing old/new values

```php
$log = Activity::find(1);

$log->old;     // ['title' => 'Old Title']
$log->new;     // ['title' => 'New Title']
$log->changes; // ['old' => [...], 'new' => [...]]
```

### 7. Display in Blade (Admin Panel)

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

## Configuration

```php
// config/activitylog.php

return [
    'default_log_name'         => 'default',
    'default_auth_driver'      => null,     // null = default guard
    'activity_model'           => \Delwarhossaindev\ActivityLog\Activity::class,
    'table_name'               => 'activity_log',
    'database_connection'      => env('ACTIVITY_LOG_DB_CONNECTION', null),
    'log_attributes_on_update' => true,
];
```

## Database Table

| Column        | Type         | Description                       |
|---------------|--------------|-----------------------------------|
| id            | bigint       | Primary key                       |
| log_name      | string       | Log channel name                  |
| description   | text         | Event description (created, etc.) |
| subject_type  | string\|null | Model class name                  |
| subject_id    | bigint\|null | Model ID                          |
| causer_type   | string\|null | User/model class name             |
| causer_id     | bigint\|null | User/model ID                     |
| properties    | json\|null   | old/new attribute values          |
| created_at    | timestamp    | When it happened                  |

## License

MIT © [Delwar Hossain](https://github.com/delwarhossaindev)
