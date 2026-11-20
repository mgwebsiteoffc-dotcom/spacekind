<?php

use App\Http\Middleware\BusinessContext;
use App\Http\Middleware\PreviewSession;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireOwner;
use App\Http\Middleware\SavePreviewState;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(prepend: [PreviewSession::class]);
        $middleware->append([SecurityHeaders::class, SavePreviewState::class]);
        $middleware->alias(['business' => BusinessContext::class, 'owner' => RequireOwner::class, 'admin' => RequireAdmin::class]);
        // Tenant context must precede route-model binding, including on API requests.
        $middleware->prependToPriorityList(SubstituteBindings::class, BusinessContext::class);
        $middleware->redirectGuestsTo(fn (Request $r) => route('login'));
        $middleware->redirectUsersTo(fn (Request $r) => $r->user()?->isAdmin() ? '/admin' : '/workspace');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
    })->create();
