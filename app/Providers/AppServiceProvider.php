<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laragear\WebAuthn\WebAuthnServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register explicitly so `auth.providers.users.driver = eloquent-webauthn` always
        // has a matching creator, even when Composer package discovery is skipped or
        // `bootstrap/cache/packages.php` is missing on deploy.
        if (class_exists(WebAuthnServiceProvider::class)) {
            $this->app->register(WebAuthnServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url');
        $forceHttps = (bool) config('app.force_https', false)
            || str_starts_with($appUrl, 'https://');

        if ($forceHttps) {
            URL::forceScheme('https');
        }

        Route::bind('product', function (string $value, \Illuminate\Routing\Route $route): Product {
            // Explicit binds run before implicit model binding, so `business` is often still the UUID string.
            $business = $route->parameter('business');
            if (! $business instanceof Business) {
                if (! is_string($business) || $business === '') {
                    abort(404);
                }
                $business = Business::query()->where('uuid', $business)->firstOrFail();
            }

            return Product::query()
                ->where('business_id', $business->getKey())
                ->where('uuid', $value)
                ->firstOrFail();
        });
    }
}
