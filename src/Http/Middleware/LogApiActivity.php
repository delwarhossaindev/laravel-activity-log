<?php

namespace Delwarhossaindev\ActivityLog\Http\Middleware;

use Closure;
use Delwarhossaindev\ActivityLog\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LogApiActivity
{
    protected ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Usage in routes:
     *   Route::middleware('log.activity')->group(...);
     *   Route::middleware('log.activity:orders')->group(...);
     */
    public function handle(Request $request, Closure $next, string $logName = null): SymfonyResponse
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->logRequest($request, $response, $logName ?? config('activitylog.api.log_name', 'api'));
        }

        return $response;
    }

    protected function shouldLog(Request $request, SymfonyResponse $response): bool
    {
        $skipMethods  = config('activitylog.api.skip_methods', []);
        $skipStatuses = config('activitylog.api.skip_statuses', []);

        if (in_array($request->method(), array_map('strtoupper', $skipMethods))) {
            return false;
        }

        if (in_array($response->getStatusCode(), $skipStatuses)) {
            return false;
        }

        return true;
    }

    protected function logRequest(Request $request, SymfonyResponse $response, string $logName): void
    {
        $properties = $this->buildProperties($request, $response);
        $description = $request->method() . ' ' . '/' . ltrim($request->path(), '/');

        $this->logger
            ->useLog($logName)
            ->withProperties($properties)
            ->log($description);
    }

    protected function buildProperties(Request $request, SymfonyResponse $response): array
    {
        $properties = [
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'ip'         => $request->ip(),
            'status'     => $response->getStatusCode(),
            'user_agent' => $request->userAgent(),
        ];

        if (config('activitylog.api.log_request_body', false)) {
            $properties['request'] = $this->sanitizeBody($request->except(
                config('activitylog.api.hide_fields', ['password', 'password_confirmation', 'token'])
            ));
        }

        if (config('activitylog.api.log_response_body', false) && $response instanceof Response) {
            $properties['response'] = $this->sanitizeBody(
                json_decode($response->getContent(), true) ?? []
            );
        }

        return $properties;
    }

    protected function sanitizeBody(array $data): array
    {
        $hidden = config('activitylog.api.hide_fields', ['password', 'password_confirmation', 'token']);

        foreach ($hidden as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }

        return $data;
    }
}
