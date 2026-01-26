<?php

namespace App\Providers;

use App\Models\Contact;
use App\Policies\ContactPolicy;
use App\Services\EvolutionApi\Client;
use App\Services\EvolutionApi\Resources\InstanceResource;
use App\Services\EvolutionApi\Resources\MessageResource;
use App\Services\EvolutionApi\Resources\WebhookResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $baseUrl = config('services.evolution_api.url') ?? '';
            $apiKey = config('services.evolution_api.key') ?? '';

            return new Client($baseUrl, $apiKey);
        });

        $this->app->singleton(InstanceResource::class, function ($app) {
            return new InstanceResource($app->make(Client::class));
        });

        $this->app->singleton(WebhookResource::class, function ($app) {
            return new WebhookResource($app->make(Client::class));
        });

        $this->app->singleton(MessageResource::class, function ($app) {
            return new MessageResource($app->make(Client::class));
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
