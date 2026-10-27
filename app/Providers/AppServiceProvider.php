<?php

namespace App\Providers;

use App\Excavation\Support\CareerTimeline;
use App\Jolpica\JolpicaClient;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\RoundNameResolver;
use App\Normalizers\Jolpica\Support\TeamResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RoundNameResolver::class, fn () => new RoundNameResolver(
            config('f1.grand_prix_names'),
            config('f1.round_overrides'),
        ));

        $this->app->bind(TeamResolver::class, fn () => new TeamResolver(config('f1.team_lineage')));

        $this->app->scoped(CoreResolver::class);

        $this->app->scoped(CareerTimeline::class);

        $this->app->scoped(JolpicaClient::class, fn () => new JolpicaClient(config('jolpica')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
