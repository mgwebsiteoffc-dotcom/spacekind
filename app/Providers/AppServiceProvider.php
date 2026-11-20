<?php

namespace App\Providers;

use App\Services\Discovery;
use App\Services\PublicTracking;
use App\Support\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class, fn () => new Tenant);
        $this->app->scoped(Discovery::class);
        $this->app->scoped(PublicTracking::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $r) {
            $raw = $r->is('demo-login') ? $r->input('role') : $r->input('email');
            $identity = is_string($raw) ? strtolower(substr($raw, 0, 300)) : 'invalid-input';

            return [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(5)->by($identity.'|'.$r->ip())];
        });
        RateLimiter::for('enquiries', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('media-uploads', fn (Request $r) => Limit::perMinute(6)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        View::composer('layouts.workspace', function ($view) {
            $view->with('studio', app(Tenant::class)->business());
        });
    }
}
