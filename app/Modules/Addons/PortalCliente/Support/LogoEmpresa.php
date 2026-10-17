<?php

namespace App\Modules\Addons\PortalCliente\Support;

use App\Modules\Core\Configuracion\Models\CompanyInformation;

/**
 * Logo de la empresa para el portal (login y barra superior).
 *
 * Sale de la información de la empresa (`company_information.url_logo`), con el logo oficial
 * versionado en el repo como respaldo — mismo orden que App\Support\LogoPdf. Solo se devuelve
 * una ruta si el archivo existe en disco, para no pintar una imagen rota. El `?v=` cambia cuando
 * se reemplaza el archivo, así el navegador no se queda con el logo anterior.
 */
class LogoEmpresa
{
    private const RESPALDO = 'images/logo_meganet_oficial.png';

    public static function url(): ?string
    {
        $candidatas = [];
        try {
            $empresa = CompanyInformation::first();
            if ($empresa && $empresa->url_logo) {
                $candidatas[] = ltrim(str_replace('\\', '/', $empresa->url_logo), '/');
            }
        } catch (\Throwable) {
            // Sin BD el login debe seguir abriendo: cae al logo de respaldo.
        }
        $candidatas[] = self::RESPALDO;

        foreach ($candidatas as $relativa) {
            $ruta = public_path($relativa);
            if (is_file($ruta)) {
                return '/' . $relativa . '?v=' . filemtime($ruta);
            }
        }

        return null;
    }
}
