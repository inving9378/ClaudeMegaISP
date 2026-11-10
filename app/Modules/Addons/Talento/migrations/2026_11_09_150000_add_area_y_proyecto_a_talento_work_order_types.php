<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1200, Fase A1 — órdenes de planta externa con campos por tipo.
 *
 * - `area`: separa los tipos de orden de cliente (instalación, garantía…) de los de planta
 *   externa (habilitar caja, montaje de fibra…). Default 'cliente': los tipos que ya existen
 *   quedan igual.
 * - `project_id`: el proyecto de la tarea sale del tipo. TYPE_PROJECT_MAP del controlador queda
 *   solo como respaldo de los tipos viejos (se retira en #1206).
 *
 * Aditiva e idempotente; down() quita solo lo que agrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('talento_work_order_types', 'area')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->string('area', 30)->default('cliente')->after('category')->index();
            });
        }

        if (! Schema::hasColumn('talento_work_order_types', 'project_id')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('area');
                $table->foreign('project_id', 'twot_project_fk')
                    ->references('id')->on('projects')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_work_order_types', 'project_id')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->dropForeign('twot_project_fk');
                $table->dropColumn('project_id');
            });
        }

        if (Schema::hasColumn('talento_work_order_types', 'area')) {
            Schema::table('talento_work_order_types', function (Blueprint $table) {
                $table->dropIndex(['area']);
                $table->dropColumn('area');
            });
        }
    }
};
