<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Listas para asignar personal solo con cuentas activas (2026-10-29): a quien deja de trabajar se
 * le marca inactivo y no se borra, así que salía en todas las listas. Agrega "solo_activos" a la
 * búsqueda de cada campo; quien ya está guardado en un registro se sigue viendo, con «(inactivo)».
 * Los módulos se resuelven por nombre (los ids no coinciden entre dev y prod). down() lo quita.
 */
return new class extends Migration
{
    // módulo => campos
    private const CAMPOS = [
        'Crm'                   => ['owner_id'],
        'CrmLeadInformation'    => ['owner_id', 'crm_techical_user_id'],
        'ClientMainInformation' => ['seller_id'],
        'Project'               => ['project_lead'],
        'Team'                  => ['users'],
        'InventoryStore'        => ['user_id'],
    ];

    public function up(): void
    {
        $this->cambiar(function (array $busqueda) {
            $busqueda['solo_activos'] = true;

            return $busqueda;
        });
    }

    public function down(): void
    {
        $this->cambiar(function (array $busqueda) {
            unset($busqueda['solo_activos']);

            return $busqueda;
        });
    }

    private function cambiar(callable $cambio): void
    {
        foreach (self::CAMPOS as $modulo => $campos) {
            $moduloId = DB::table('modules')->where('name', $modulo)->value('id');
            if (! $moduloId) {
                continue;
            }
            $filas = DB::table('field_modules')
                ->where('module_id', $moduloId)
                ->whereIn('name', $campos)
                ->get(['id', 'search']);
            foreach ($filas as $fila) {
                $busqueda = json_decode((string) $fila->search, true);
                // Solo las búsquedas de personal; otra cosa no se toca.
                if (! is_array($busqueda) || ltrim($busqueda['model'] ?? '', '\\') !== 'App\Models\User') {
                    continue;
                }
                DB::table('field_modules')->where('id', $fila->id)->update([
                    'search'     => json_encode($cambio($busqueda), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
