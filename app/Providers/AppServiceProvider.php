<?php

namespace App\Providers;

use App\Actions\Ai\GeminiAiClient;
use App\Models\ActionSuggestionSetting;
use App\Models\AiReport;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Policies\ActionSuggestionPolicy;
use App\Policies\AiReportPolicy;
use App\Policies\AnalyticsPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
        Relation::morphMap([
            'lead' => Lead::class,
            'client' => Client::class,
            'opportunity' => Opportunity::class,
        ]);

        Gate::policy(GeminiAiClient::class, AnalyticsPolicy::class);
        Gate::policy(ActionSuggestionSetting::class, ActionSuggestionPolicy::class);
        Gate::policy(AiReport::class, AiReportPolicy::class);
    }
}
