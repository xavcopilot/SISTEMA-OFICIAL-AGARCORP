<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MainDashboard;
use App\Filament\Widgets\Dashboard\DesktopWelcomeWidget;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgarcorpPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
                $panelTitle = 'Sistema de Gestión AGARCORP';

        return $panel
            ->default()
            ->id('agarcorp')
            ->path('agarcorp')
            ->login()
            ->brandName($panelTitle)
            ->brandLogo(asset('images/logo-agarcorp.png'))
            ->brandLogoHeight('6.5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('5rem')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                MainDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                DesktopWelcomeWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                \App\Http\Middleware\RedirectReceptionUserToRecep::class,
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
                    <span class='ag-topbar-panel-title tracking-wide'>
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
            <div class='ag-top-module-wrap'>
                <span class='ag-top-module-chip'>
                    <span class='ag-top-module-icon'>✦</span>
                    <span class='ag-top-module-label'>Panel</span>
                    <span class='ag-top-module-value'>" . e($roleName) . "</span>
                </span>
            </div>
        ";
    }
)
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\TrackLastFilamentModule::class,
            ]);
    }
}
