<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retiro del catálogo viejo de "servicios contratables" (Item #006, overlay).
 *
 * Sistema inerte: pantalla admin /planes/catalogo navegable pero NINGÚN flujo
 * downstream consumía la tabla `contractable_services` (ni facturación, ni portal,
 * ni activación de módulos). Se retira para liberar el slot "Planes → Servicios
 * contratables" en favor del sistema nuevo (contratable_*, F1/F2, cableado a
 * facturación). NO toca nada de contratable_* (español).
 *
 * Aditiva y REVERSIBLE: down() recrea la tabla y los permisos tal como los creó la
 * migración original 2026_06_01_000001 (la reasignación a roles la haría
 * permissions:sync-roles, igual que en su alta original).
 */
return new class extends Migration
{
    private array $perms = ['plan_view_catalog', 'plan_sync_catalog', 'plan_edit_catalog'];

    public function up(): void
    {
        // 1) Quitar asignaciones a roles + permisos del catálogo viejo.
        $ids = DB::table('permissions')->whereIn('name', $this->perms)
            ->where('guard_name', 'web')->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        // 2) Eliminar la tabla overlay (nada la consume).
        Schema::dropIfExists('contractable_services');

        // 3) Limpiar cache de permisos Spatie.
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // cache opcional; no bloquea la migración
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractable_services')) {
            Schema::create('contractable_services', function (Blueprint $table) {
                $table->id();
                $table->string('service_type_key')->unique();
                $table->string('module_slug');
                $table->string('label');
                $table->decimal('price', 12, 2)->nullable();
                $table->boolean('price_configurable')->default(false);
                $table->boolean('supports_promotions')->default(false);
                $table->boolean('bundleable')->default(false);
                $table->boolean('active')->default(true);
                $table->json('meta')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        foreach ($this->perms as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name'       => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }
};
