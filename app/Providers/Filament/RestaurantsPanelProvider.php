<?php

namespace App\Providers\Filament;

use App\Filament\Restaurants\Pages\Dashboard;
use App\Models\Restaurant;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\FontFamily;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin;

class RestaurantsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->default()
            ->brandName(fn() => auth()->user()?->restaurants()->first()?->name_comercial ?? 'Mi Restaurante')
            ->brandLogo(function () {
                $user = Auth::user();
                $restaurant = $user?->restaurants()->first();

                if ($restaurant && $restaurant->logo) {
                    return asset('storage/' . $restaurant->logo);
                }
                return asset('img/mi-restaurant.png');
            })
            ->brandLogoHeight('3.5rem')
            ->favicon('/img/restaurant-favicon.ico')
            ->path('app')
            // ->profile()
            ->login()
            // ->colors([
            //     'danger' => '#ED3F27',
            //     'gray' => '#31363F',
            //     'info' => '#1A2A4F',
            //     'primary' => '#1A2A4F',
            //     'success' => '#73AF6F',
            //     'warning' => '#FEB21A',
            // ])
            ->font('Vend Sans', provider: GoogleFontProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::body.start', fn() => '
                <style>
                /* ===== MODO CLARO ===== */
                html.dark {
                    .fi-sidebar-item.fi-active .fi-sidebar-item-label, .fi-tabs-item-label, .choices__item{
                        color: white;
                    }
                    .fi-sidebar-item-icon{
                        color: white;
                    }
                    .fi-sidebar-nav{
                        border-right: 1px solid #393E46;
                    }
                }

                .fi-sidebar-header .fi-icon-btn{
                    border: 1px solid #393E46;
                    color: white;
                }

                .fi-page-sub-navigation-sidebar-ctn {
                    width: 10rem !important;
                }
                
                html:not(.dark) {

                    /* Sidebar principal */
                    .fi-sidebar-nav,
                    .fi-sidebar-header,
                    .fi-topbar nav {
                        background-color: #1A2A4F;
                    }

                    .fi-sidebar-nav {
                        box-shadow: 4px 0 12px rgba(0, 0, 0, 0.25);
                        box-sizing: border-box;
                    }

                    /* Texto general del sidebar */
                    .fi-sidebar-item-label,
                    .fi-sidebar-group-label,
                    .fi-sidebar-item-icon,
                    .fi-dropdown-trigger span {
                        color: white;
                    }
                    
                    .fi-ta-header-toolbar .fi-btn .fi-btn-label {
                        color: #1A2A4F;
                    }

                    /* Hover */
                    .fi-sidebar-item:hover .fi-sidebar-item-label,
                    .fi-sidebar-item:hover .fi-sidebar-item-icon,
                    .fi-dropdown-trigger:hover span {
                        color: #1A2A4F;
                    }

                    /* Activo */
                    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
                    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
                        color: #1A2A4F;
                    }

                    /* Subnavegación */
                    .fi-page-sub-navigation-sidebar-ctn {
                        background-color: white;
                        border-radius: 8px;
                        border: 1px solid #E2E8F0;
                        padding: 8px;
                    }

                    .fi-page-sub-navigation-sidebar-ctn .fi-sidebar-item-icon,
                    .fi-page-sub-navigation-sidebar-ctn .fi-sidebar-item-label {
                        color: #1A2A4F;
                    }
                }
                </style>
            ')
            ->discoverResources(in: app_path('Filament/Restaurants/Resources'), for: 'App\\Filament\\Restaurants\\Resources')
            ->discoverPages(in: app_path('Filament/Restaurants/Pages'), for: 'App\\Filament\\Restaurants\\Pages')
            ->pages([
                // Pages\Dashboard::class,
                Dashboard::class
            ])
            ->discoverWidgets(in: app_path('Filament/Restaurants/Widgets'), for: 'App\\Filament\\Restaurants\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa()// para que las páginas se carguen sin refrescar toda la página
            ->assets([
            // Cargamos el script aquí para que esté disponible GLOBALMENTE
                Js::make('mesas-script', asset('js/mesas.js')), 
                Js::make('orden-mesa-script', asset('js/ordenmesa.js')), 
            ])
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->unsavedChangesAlerts()//para mostrar alertas de cambios no guardados
            ->databaseTransactions()//para realizar transacciones en las páginas de recursos
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->tenant(Restaurant::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.restaurantetenacy.test')
            ->plugin(FilamentProgressbarPlugin::make()->color('#29b'));
    }
}
