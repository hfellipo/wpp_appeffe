<?php

namespace App\Providers;

use App\Models\Contact;
use App\Policies\ContactPolicy;
use App\Services\EvolutionApiHttpClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar o novo cliente HTTP da Evolution API
        $this->app->singleton(EvolutionApiHttpClient::class, function () {
            return new EvolutionApiHttpClient();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Contact::class, ContactPolicy::class);

        /**
         * Production safety:
         * If a "public/hot" file is accidentally deployed, Laravel will assume Vite dev-server
         * is running and will try to load assets from it, causing broken/un-styled pages.
         *
         * For any non-local environment, force Vite to look for the hot file in a path that
         * won't exist on typical servers, so we always use the built assets in public/build.
         */
        if (! app()->environment('local')) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }
    }
}
