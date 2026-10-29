<?php

namespace App\Providers;

use App\Excavation\Support\CareerTimeline;
use App\Jolpica\JolpicaClient;
use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Jolpica\Support\RoundNameResolver;
use App\Normalizers\Jolpica\Support\TeamResolver;
use App\Normalizers\OpenF1\Support\DriverMatcher;
use App\Normalizers\OpenF1\Support\MeetingMatcher;
use App\Normalizers\OpenF1\Support\SessionMatcher;
use App\OpenF1\OpenF1Client;
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

        $this->app->scoped(OpenF1Client::class, fn () => new OpenF1Client(config('openf1')));

        $this->app->bind(MeetingMatcher::class, fn () => new MeetingMatcher(
            config('openf1.circuits'),
            config('openf1.date_tolerance_hours'),
        ));

        $this->app->bind(SessionMatcher::class, fn ($app) => new SessionMatcher(
            $app->make(CoreResolver::class),
            config('openf1.session_types'),
            config('openf1.date_tolerance_hours'),
        ));

        $this->app->bind(DriverMatcher::class, fn ($app) => new DriverMatcher(
            $app->make(CoreResolver::class),
            config('openf1.driver_overrides'),
            config('openf1.teams'),
            config('openf1.practice_only_drivers'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
