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
use App\Models\User;
use App\Support\AccessControl;
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
