<?php

namespace App\Providers;

use App\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\MetaCloudApiGateway;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class, fn () => new CurrentWorkspace);

        $this->app->bind(WhatsAppGatewayInterface::class, function () {
            return match (config('services.whatsapp.driver', 'log')) {
                'meta' => new MetaCloudApiGateway,
                default => new LogWhatsAppGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
