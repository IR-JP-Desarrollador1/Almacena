<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationAccess;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, [RecordAuthenticationAccess::class, 'handleLogin']);
        Event::listen(Failed::class, [RecordAuthenticationAccess::class, 'handleFailed']);
        Event::listen(Lockout::class, [RecordAuthenticationAccess::class, 'handleLockout']);
    }
}
