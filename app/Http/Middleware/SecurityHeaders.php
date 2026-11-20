<?php

namespace App\Http\Middleware;

use App\Services\Discovery;
use App\Services\PublicTracking;
use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $r, Closure $next)
    {
        $response = $next($r);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($r->is('document-share/*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $origins = $response->getStatusCode() === 200 ? app(PublicTracking::class)->csp($r) : ['script' => [], 'connect' => [], 'image' => []];
        $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob: ".implode(' ', $origins['image'])."; media-src 'self' blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' ".implode(' ', $origins['script'])."; connect-src 'self' ".implode(' ', $origins['connect'])."; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'");
        if ($response->getStatusCode() >= 400 || ! app(Discovery::class)->indexable($r)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($r->user() || $r->is('workspace*', 'admin*', 'api/*', 'login', 'register', 'project-brief', 'studios/*/enquire*', 'article-media/*', 'reel-media/*', 'media/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
