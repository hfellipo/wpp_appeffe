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
         * Vite safety for production/shared hosting.
         *
         * Common pitfalls that break CSS/JS in production:
         * - Deploying a leftover "public/hot" file (Laravel thinks Vite dev-server is running).
         * - Serving Laravel from a subpath like "/public" (document root is the repo root).
         *
         * We handle both robustly:
         * - Never use Vite hot outside localhost, even if APP_ENV is misconfigured.
         * - Prefix asset URLs with the request base path when needed (e.g. /public/build/...).
         */
        if (! app()->runningInConsole()) {
            $host = request()->getHost();
            $isLocalHost = in_array($host, ['127.0.0.1', 'localhost'], true);

            if (! $isLocalHost) {
                // If APP_ENV accidentally stays "local" on the server, this still prevents broken assets.
                Vite::useHotFile(storage_path('framework/vite.hot'));
            }

            $basePath = (string) request()->getBasePath(); // e.g. "" or "/public"
            $basePath = rtrim($basePath, '/');
            if ($basePath !== '') {
                Vite::createAssetPathsUsing(function (string $path) use ($basePath) {
                    // Avoid relying on APP_URL (can be misconfigured with /public and cause /public/public).
                    $path = ltrim($path, '/'); // e.g. "build/assets/app-xxx.css"
                    return rtrim(request()->getSchemeAndHttpHost().$basePath, '/').'/'.$path;
                });
            }
        } elseif (! app()->environment('local')) {
            // Console safety for real production envs.
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }
    }
}
