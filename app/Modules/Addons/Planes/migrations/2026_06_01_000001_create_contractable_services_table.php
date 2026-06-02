<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #006 — Motor de servicios contratables.
 *
 * Capa de overlay administrable sobre los `service_type` declarados por los
 * módulos en su module.json. Las capacidades (bundleable, supports_promotions,
 * price_configurable) provienen del manifiesto; aquí se persiste lo que el
 * admin configura: precio, etiqueta personalizada, activo/inactivo y meta.
 *
 * El catálogo unificado se reconstruye en ServiceCatalogService::catalog()
 * fusionando manifiesto (autoritativo en capacidades) + esta tabla (overlay).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractable_services')) {
            Schema::create('contractable_services', function (Blueprint $table) {
                $table->id();
                $table->string('service_type_key')->unique();   // ej. 'megafamilia', 'internet'
                $table->string('module_slug');                   // ej. 'addon-megafamilia'
                $table->string('label');
                $table->decimal('price', 12, 2)->nullable();     // precio base de contratación
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

        // Permisos del catálogo (idempotente). Admin/Developer los saltan, pero
        // se registran para gating de roles operativos vía CheckRoutePermission.
        $now = now();
        foreach (['plan_view_catalog' => 'Ver catálogo de servicios contratables',
                  'plan_sync_catalog' => 'Sincronizar catálogo desde manifiestos de módulos',
                  'plan_edit_catalog' => 'Editar precio/estado de servicios contratables'] as $name => $_desc) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('contractable_services');
        DB::table('permissions')
            ->whereIn('name', ['plan_view_catalog', 'plan_sync_catalog', 'plan_edit_catalog'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
