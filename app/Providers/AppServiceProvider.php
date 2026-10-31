<?php

namespace App\Providers;

use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundCrashed;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Game\Listeners\AutoCashoutOnTick;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Wallet\Listeners\WebhookOnGameEvents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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
        // Behind Cloudflare the app itself is reached over plain http, so
        // asset()/@vite would emit http:// URLs that browsers block on the
        // https page. An https APP_URL means every generated URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $shortName = class_basename($modelName);

            return "Database\\Factories\\{$shortName}Factory";
        });

        Event::listen(MultiplierTick::class, AutoCashoutOnTick::class);

        Event::listen(BetPlaced::class, [WebhookOnGameEvents::class, 'onBetPlaced']);
        Event::listen(CashoutCompleted::class, [WebhookOnGameEvents::class, 'onCashoutCompleted']);
        Event::listen(RoundCrashed::class, [WebhookOnGameEvents::class, 'onRoundCrashed']);
        Event::listen(RoundSettled::class, [WebhookOnGameEvents::class, 'onRoundSettled']);
    }
}
