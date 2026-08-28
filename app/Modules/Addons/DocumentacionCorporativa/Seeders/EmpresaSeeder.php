<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Seeders;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siembra la empresa emisora del expediente. IDEMPOTENTE por `razon_social`.
 *
 * Toma razón social y RFC de `company_information` SÓLO si están poblados. Todo
 * lo demás nace vacío y editable: no se inventa un RFC ni una fecha de
 * constitución. Un dato inventado en un expediente corporativo es peor que un
 * campo en blanco, porque nadie vuelve a revisarlo.
 */
class EmpresaSeeder extends Seeder
{
    public const RAZON_SOCIAL_DEFECTO = 'MEGANET Telecomunicaciones S.A. de C.V.';

    public function run(): void
    {
        $origen = $this->companyInformation();

        $razonSocial = $this->limpio($origen['company_name'] ?? null) ?? self::RAZON_SOCIAL_DEFECTO;
        $rfc         = $this->limpio($origen['rfc'] ?? null);

        DcEmpresa::firstOrCreate(
            ['razon_social' => $razonSocial],
            [
                'nombre_comercial'   => null,
                'rfc'                => $rfc,
                'regimen_fiscal'     => null,
                'fecha_constitucion' => null,
                'domicilio_fiscal'   => $this->domicilio($origen),
                'activo'             => true,
            ]
        );
    }

    /** Fila única de configuración de la empresa, si la tabla existe. */
    private function companyInformation(): array
    {
        if (! Schema::hasTable('company_information')) {
            return [];
        }

        $fila = DB::table('company_information')->whereNull('deleted_at')->first();

        return $fila ? (array) $fila : [];
    }

    /**
     * Domicilio armado con lo que haya. Devuelve null si no hay ninguna pieza:
     * un domicilio a medias es peor que ninguno para un expediente legal.
     */
    private function domicilio(array $o): ?string
    {
        $partes = array_filter([
            $this->limpio($o['company_street'] ?? null),
            $this->limpio($o['company_external_number'] ?? null),
            $this->limpio($o['company_internal_number'] ?? null),
            $this->limpio($o['company_postal_code'] ?? null),
            $this->limpio($o['country'] ?? null),
        ]);

        return $partes === [] ? null : implode(' ', $partes);
    }

    private function limpio($valor): ?string
    {
        $valor = $valor === null ? null : trim((string) $valor);

        return ($valor === null || $valor === '') ? null : $valor;
    }
}
