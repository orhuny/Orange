<?php

namespace App\Providers;

use App\Domain\Game\Engines\Contracts\GameEngine;
use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\BetSettled;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundCrashed;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Game\Listeners\AutoCashoutOnTick;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Wallet\Listeners\WebhookOnGameEvents;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(EngineRegistry::class, function (Application $app): EngineRegistry {
            // Crash as a literal fallback, so a config cache built before config/games.php
            // existed can't stop the live game.
            /** @var array<string, class-string<GameEngine>> $engines */
            $engines = (array) config('games.engines', ['crash' => CrashEngine::class]);

            return new EngineRegistry($app, $engines);
        });
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
        Event::listen(BetSettled::class, [WebhookOnGameEvents::class, 'onBetSettled']);

        // The one admin-panel permission check (spatie's own gate hook is disabled in
        // config/permission.php). Order matters: operator users can never exceed the
        // operator permission set whatever role they hold; Super Admins pass
        // everything; everyone else needs the permission through a role.
        Gate::before(function (User $user, string $ability): ?bool {
            if (! array_key_exists($ability, AccessControl::flat())) {
                return null;
            }
            if ($user->isOperatorUser() && ! in_array($ability, AccessControl::OPERATOR_PERMISSIONS, true)) {
                return false;
            }
            if (AccessControl::isSuperAdmin($user)) {
                return true;
            }

            return $user->checkPermissionTo($ability);
        });
    }
}
