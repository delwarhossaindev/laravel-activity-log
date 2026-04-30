<?php

namespace Delwarhossaindev\ActivityLog\Http\Middleware;

use Closure;
use Delwarhossaindev\ActivityLog\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * LogApiActivity — Middleware that automatically logs every HTTP request.
 *
 * Attach this to any route or group in routes/api.php and every request
 * will be recorded in the activity_log table — no controller changes needed.
 *
 * Registration (done automatically by the service provider):
 *   Route alias: 'log.activity'
 *
 * Usage in routes/api.php:
 *   // Log all routes in a group
 *   Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
 *       Route::apiResource('posts', PostController::class);
 *   });
 *
 *   // Log a single route
 *   Route::post('/orders', [OrderController::class, 'store'])
 *        ->middleware('log.activity');
 *
 *   // Log into a custom channel ('orders' instead of 'api')
 *   Route::middleware('log.activity:orders')->group(...);
 *
 * Each log entry stores: method, full URL, client IP, HTTP status, user-agent.
 * Optionally the request body can also be stored (see config/activitylog.php).
 */
class LogApiActivity
{
    protected ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Run the middleware.
     *
     * The request is passed to the next handler first, then logged AFTER
     * the response is ready so we can capture the HTTP status code.
     *
     * @param  string|null  $logName  Optional channel name passed from the route
     *                                definition (e.g. 'log.activity:orders').
     */
    public function handle(Request $request, Closure $next, string $logName = null): SymfonyResponse
    {
        // Let the request go through and get a response first
        $response = $next($request);

        // Only log if this request/response passes the skip rules
        if ($this->shouldLog($request, $response)) {
            $channel = $logName ?? config('activitylog.api.log_name', 'api');
            $this->logRequest($request, $response, $channel);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Decide whether this request should be logged.
     *
     * Two reasons to skip:
     *   1. The HTTP method is in the 'skip_methods' config list (e.g. GET, HEAD).
     *   2. The response status code is in the 'skip_statuses' config list.
     */
    protected function shouldLog(Request $request, SymfonyResponse $response): bool
    {
        $skipMethods  = config('activitylog.api.skip_methods', []);
        $skipStatuses = config('activitylog.api.skip_statuses', []);

        // Compare case-insensitively by normalising both sides to uppercase
        if (in_array($request->method(), array_map('strtoupper', $skipMethods))) {
            return false;
        }

        if (in_array($response->getStatusCode(), $skipStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Build the log entry and save it via ActivityLogger.
     *
     * The description is "METHOD /path" (e.g. "POST /api/orders").
     * Extra properties (IP, status, body, etc.) go into the properties column.
     */
    protected function logRequest(Request $request, SymfonyResponse $response, string $logName): void
    {
        $properties  = $this->buildProperties($request, $response);
        $description = $request->method() . ' ' . '/' . ltrim($request->path(), '/');

        $this->logger
            ->useLog($logName)
            ->withProperties($properties)
            ->log($description);
    }

    /**
     * Assemble the properties array that is stored in the 'properties' JSON column.
     *
     * Always included:
     *   method, url, ip, status, user_agent
     *
     * Optional (enabled in config/activitylog.php):
     *   request  → the request body (with sensitive fields masked)
     *   response → the response body (JSON responses only)
     */
    protected function buildProperties(Request $request, SymfonyResponse $response): array
    {
        $properties = [
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'ip'         => $request->ip(),
            'status'     => $response->getStatusCode(),
            'user_agent' => $request->userAgent(),
        ];

        // Optionally store the request body — useful for payment/order APIs
        if (config('activitylog.api.log_request_body', false)) {
            $hiddenFields = config('activitylog.api.hide_fields', [
                'password', 'password_confirmation', 'token',
            ]);
            $properties['request'] = $this->sanitizeBody($request->except($hiddenFields));
        }

        // Optionally store the response body (JSON only)
        if (config('activitylog.api.log_response_body', false) && $response instanceof Response) {
            $properties['response'] = $this->sanitizeBody(
                json_decode($response->getContent(), true) ?? []
            );
        }

        return $properties;
    }

    /**
     * Replace sensitive field values with '***' so they are never stored in plain text.
     *
     * The list of fields to mask comes from config('activitylog.api.hide_fields').
     * Default masked fields: password, password_confirmation, token.
     *
     * Example:
     *   Input:  ['email' => 'user@example.com', 'password' => 'secret']
     *   Output: ['email' => 'user@example.com', 'password' => '***']
     */
    protected function sanitizeBody(array $data): array
    {
        $hidden = config('activitylog.api.hide_fields', [
            'password', 'password_confirmation', 'token',
        ]);

        foreach ($hidden as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }

        return $data;
    }
}
