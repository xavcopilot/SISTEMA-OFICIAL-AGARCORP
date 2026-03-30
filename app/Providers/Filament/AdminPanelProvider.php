<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
                $panelTitle = 'Sistema de Gestión AGARCORP';

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName($panelTitle)
            ->brandLogo(asset('images/logo-agarcorp.png'))
            ->brandLogoHeight('6.5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Administracion')
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.login-header')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.login-footer')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_BEFORE,
                fn (): string => "
                    <style>
                        .fi-topbar { padding-inline-start: 0.5rem !important; }
                        .fi-topbar-start { gap: 0.5rem !important; }
                    </style>
                "
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => "
                    <span class='hidden md:inline-block tracking-wide' style='font-size: 2rem; font-weight: 800; line-height: 1; color: #0e3398 !important;'>
                        {$panelTitle}
                    </span>
                "
            )
           ->renderHook(
       'panels::user-menu.before',
        function (): string {
        $user = auth()->user();
        $roleName = $user?->getRoleNames()->first() ?? 'ROL SIN ASIGNAR (CONSULTAR A TECNICO)';

        // Si el rol es 'administrador' o 'super_admin', lo renombramos a 'Administrador'
        // pero mantenemos el diseño exacto que tenías antes.
        if (in_array(strtolower($roleName), ['administrador', 'super_admin'])) {
            $roleName = 'Administrador';
        }

        return "
            <div class='flex items-center gap-2 mr-4'>
                <span class='px-3 py-1 text-xs font-bold text-white bg-blue-900 rounded-full shadow-sm uppercase'>
                    🏢 Módulo: " . $roleName . "
                </span>
            </div>
        ";
    }
)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
