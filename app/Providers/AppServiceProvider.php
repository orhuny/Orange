<?php

namespace App\Providers;

use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Listeners\AutoCashoutOnTick;
use App\Domain\Game\Services\MultiplierCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            MultiplierCalculator::class,
            fn () => MultiplierCalculator::default(),
        );
    }

    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $shortName = class_basename($modelName);

            return "Database\\Factories\\{$shortName}Factory";
        });

        Event::listen(MultiplierTick::class, AutoCashoutOnTick::class);
    }
}
