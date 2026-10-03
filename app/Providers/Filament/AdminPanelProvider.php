<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\GlobalKpiWidget;
use App\Filament\Widgets\TodayRoundsChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
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
            ->brandName('Orange Provider')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
            ])
            // SPA mode — turns nav into Livewire navigate, eliminates full page
            // reloads between resources. Massive perceived-speed win for users
            // bouncing between Bets / Rounds / Operators.
            ->spa()
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            // Surface queued-job notifications (export completion, etc.) in the
            // top-right bell. 30s polling — internal tool, freshness within
            // 30s is plenty and cuts dashboard DB load by 3x.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('Operations')->icon('heroicon-o-building-office-2'),
                NavigationGroup::make('Game')->icon('heroicon-o-rocket-launch'),
                NavigationGroup::make('Fairness')->icon('heroicon-o-shield-check'),
                NavigationGroup::make('System')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                GlobalKpiWidget::class,
                TodayRoundsChart::class,
                AccountWidget::class,
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
