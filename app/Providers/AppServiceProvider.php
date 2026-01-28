<?php

namespace App\Providers;

use App\Models\Contact;
use App\Policies\ContactPolicy;
use App\Services\EvolutionApiHttpClient;
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
    }
}
