<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireOwner
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->isOwner(), 403, 'Only the studio owner can manage this area.');

        return $next($r);
    }
}
