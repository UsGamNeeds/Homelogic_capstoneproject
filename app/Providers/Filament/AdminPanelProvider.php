<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Navigation\CustomNavigationProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->darkMode()
            ->spa()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\HeroSectionWidget::class,
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\QuickActionsWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigation(CustomNavigationProvider::class)
            // Removed navigationGroups to prevent auto-discovery from creating groups
            ->topNavigation()
            ->brandName('Evergreen Oasis Care Home')
            ->brandLogo(asset('images/logo.jpeg'))
            ->brandLogoHeight('2.5rem')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Dashboard' => 'Dashboard',
                'Resident Care' => 'Resident Care',
                'Reports & Analytics' => 'Reports & Analytics',
                'Administration' => 'Administration',
            ])
            ->userMenuItems([]) // Disable default user menu items
            ->renderHook(
                'panels::topbar.end',
                fn (): string => view('filament.components.user-menu'),
            )
            ->renderHook(
                'panels::topbar.start',
                fn (): string => '
                    <link rel="stylesheet" href="' . asset('css/custom-enhancements.css') . '">
                    <style>
                        /* Hide Filament default user menu component */
                        [data-filament-name="account-widget"],
                        [wire\\:key*="account-widget"],
                        .fi-account-menu,
                        [x-data*="accountMenu"],
                        .fi-topbar-actions > button:last-child,
                        .fi-topbar-actions > div:last-child button {
                            display: none !important;
                        }
                    </style>
                ',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
