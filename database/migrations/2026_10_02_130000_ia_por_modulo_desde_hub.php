<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IA por módulo desde el Integration Hub:
 * - El catálogo de proveedores sabe qué protocolo habla cada proveedor de IA
 *   (driver: claude | openai | openai_compatible | gemini) y si lee imágenes/PDF.
 * - Cada módulo (ia_asignaciones.clave) apunta a una INTEGRACIÓN del Hub
 *   (llave) + modelo. Sin asignación = el módulo no usa IA (decisión de Irving).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('api_integration_providers', 'driver')) {
            Schema::table('api_integration_providers', function (Blueprint $table) {
                $table->string('driver', 30)->nullable()->after('type');
                $table->boolean('soporta_imagenes')->default(false)->after('driver');
                $table->boolean('soporta_pdf')->default(false)->after('soporta_imagenes');
            });
        }

        DB::table('api_integration_providers')->where('slug', 'anthropic')->whereNull('driver')
            ->update(['driver' => 'claude', 'soporta_imagenes' => true, 'soporta_pdf' => true]);
        DB::table('api_integration_providers')->where('slug', 'openai')->whereNull('driver')
            ->update(['driver' => 'openai', 'soporta_imagenes' => true, 'soporta_pdf' => true]);

        if (Schema::hasTable('ia_asignaciones') && !Schema::hasColumn('ia_asignaciones', 'api_integration_id')) {
            Schema::table('ia_asignaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('api_integration_id')->nullable()->after('clave');
                $table->foreign('api_integration_id')->references('id')->on('api_integrations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ia_asignaciones', 'api_integration_id')) {
            Schema::table('ia_asignaciones', function (Blueprint $table) {
                $table->dropForeign(['api_integration_id']);
                $table->dropColumn('api_integration_id');
            });
        }
        if (Schema::hasColumn('api_integration_providers', 'driver')) {
            Schema::table('api_integration_providers', function (Blueprint $table) {
                $table->dropColumn(['driver', 'soporta_imagenes', 'soporta_pdf']);
            });
        }
    }
};
