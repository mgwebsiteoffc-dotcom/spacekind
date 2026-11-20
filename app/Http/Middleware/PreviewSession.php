<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** HTTPS preview embedding only. Production keeps its normal first-party cookie policy. */
class PreviewSession
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('spacekind.demo') || ! $request->isSecure() || ! str_ends_with($request->getHost(), '.e2b.app')) {
            return $next($request);
        }
        $previous = config('session');
        config(['session.secure' => true, 'session.same_site' => 'none', 'session.partitioned' => true]);
        try {
            return $next($request);
        } finally {
            config(['session' => $previous]);
        }
    }
}
