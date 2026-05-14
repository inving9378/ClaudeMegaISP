<?php

namespace App\Modules\Core\Layout;

use App\Models\CompanyInformation;
use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\Layout\Models\AppLayoutConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-layout';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-layout';

    public function boot(): void
    {
        parent::boot();

        if (! $this->moduleIsActive()) {
            return;
        }

        // Compartidas con cualquier vista que extienda core-layout::master.
        // Movidas desde AppServiceProvider::boot() como parte de la migración del módulo.

        View::share('configLayout', function ($id) {
            $configLayout = null;
            if (Auth::check()) {
                $configLayout = AppLayoutConfiguration::where('user_id', $id)->first();
            }
            return $configLayout;
        });

        View::share('logoMeganet', function () {
            // CompanyInformation se moverá a Core/Configuracion en un PR siguiente.
            $companyInformation = CompanyInformation::first();
            if ($companyInformation) {
                return [
                    'name' => $companyInformation->logo,
                    'url_logo' => $companyInformation->url_logo,
                ];
            }
            return [
                'name' => null,
                'url_logo' => '',
            ];
        });
    }
}
