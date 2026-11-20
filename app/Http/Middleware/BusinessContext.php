<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;

class BusinessContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && ! $user->isAdmin(), 403, 'An active studio account is required.');
        $business = $user->business()->first(); // Re-read access state rather than trusting a cached relation.
        abort_unless($business && $business->account_status === 'pilot', 403, 'This workspace is suspended. Please contact the platform administrator.');

        return app(Tenant::class)->run($business, fn () => $next($request));
    }
}
