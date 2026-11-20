<?php

namespace App\Http\Middleware;

use App\Services\PreviewState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SavePreviewState
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return;
        }
        try {
            app(PreviewState::class)->save();
        } catch (\Throwable $e) {
            Log::warning('Local preview recovery snapshot failed. MySQL remains authoritative.', ['error' => $e->getMessage()]);
        }
    }
}
