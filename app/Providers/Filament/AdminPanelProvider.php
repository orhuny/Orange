<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\GameKpiTable;
use App\Filament\Widgets\GlobalKpiWidget;
use App\Filament\Widgets\TodayRoundsChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // The panel is branded Majestic Games; the project and the app stay "Orange".
            ->brandName('Majestic Games')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/majestic/favicon.svg'))
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->font('Inter')
            // The panel's finishing (public/css/majestic-admin.css): plain CSS on Filament's
            // fi-* hooks, so it needs no asset build on deploy.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<link rel="stylesheet" href="'
                .asset('css/majestic-admin.css').'?v='.filemtime(public_path('css/majestic-admin.css')).'">')
            // SPA mode — turns nav into Livewire navigate, eliminates full page
            // reloads between resources. Massive perceived-speed win for users
            // bouncing between Bets / Rounds / Operators.
            ->spa()
            ->maxContentWidth(Width::Full)
            ->sidebarWidth('16.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            // Surface queued-job notifications (export completion, etc.) in the
            // top-right bell. 30s polling — internal tool, freshness within
            // 30s is plenty and cuts dashboard DB load by 3x.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Groups carry no icons, so each item shows its own.
            ->navigationGroups([
                NavigationGroup::make('Catalogue'),
                NavigationGroup::make('Activity'),
                NavigationGroup::make('Operators'),
                NavigationGroup::make('Fairness'),
                NavigationGroup::make('Administration'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                GlobalKpiWidget::class,
                GameKpiTable::class,
                TodayRoundsChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
