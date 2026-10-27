<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request, so the logistics rates and zones it loads are read once and reset after a change.
        $this->app->scoped(\App\Services\ShippingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A22 for the mobile app: like a web session, an app login ends after 30 minutes without use.
        // (Checked before Sanctum stamps last_used_at for this request.)
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(fn ($token, bool $isValid) => $isValid
            && ($token->last_used_at ?? $token->created_at)?->gt(now()->subMinutes(\App\Http\Controllers\Api\V1\AuthController::IDLE_MINUTES)));

        // A rider who logs out is Offline and gets no more job offers.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \App\Models\Rider::where('user_id', $event->user->id)->update(['is_online' => false]);
            }
        });
    }
}
