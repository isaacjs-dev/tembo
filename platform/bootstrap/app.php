<?php

use App\Http\Middleware\CheckInstitutionPermission;
use App\Http\Middleware\CheckOmrApiAccess;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureVerifiedAccount;
use App\Http\Middleware\RestrictLogAccess;
use App\Http\Middleware\RestrictTrashAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'inst_perm' => CheckInstitutionPermission::class,
            'restrict_trash' => RestrictTrashAccess::class,
            'restrict_logs' => RestrictLogAccess::class,
            'omr_api' => CheckOmrApiAccess::class,
            'active_account' => EnsureActiveAccount::class,
            'verified_account' => EnsureVerifiedAccount::class,
            'password_changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
