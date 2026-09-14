<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * #9991163 — Permisos de los adjuntos del roadmap (punto 4): ver/descargar, subir, borrar.
 * `syncPermissionToBaseRoles` los da a super-administrator y DESARROLLADOR (como siempre); el
 * auto-grant de `.view` a los demás roles está apagado en config/permission_sync.php, así que
 * nadie más los recibe por accidente.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'roadmap.adjuntos.view'   => 'Ver y descargar adjuntos del roadmap (maquetas, capturas, PDFs) desde la Torre',
        'roadmap.adjuntos.upload' => 'Subir adjuntos del roadmap y amarrarlos a items',
        'roadmap.adjuntos.delete' => 'Borrar (lógicamente) adjuntos del roadmap',
    ];

    public function up(): void
    {
        $sync = app(PermissionSyncService::class);
        foreach (self::PERMISOS as $nombre => $descripcion) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web'], ['description' => $descripcion]);
            $sync->syncPermissionToBaseRoles($nombre);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISOS))->delete();
    }
};
