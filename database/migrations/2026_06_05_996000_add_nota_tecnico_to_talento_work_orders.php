<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // talento_work_orders vive en el módulo Talento (app/Modules/Addons/Talento/migrations),
        // no en database/migrations: en la reconstrucción aislada de schema:rebuild-dryrun esa
        // migración no corre, así que la tabla puede no existir todavía. En dev/prod reales sí existe.
        if (!Schema::hasTable('talento_work_orders')) {
            return;
        }

        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->text('nota_tecnico')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('talento_work_orders')) {
            return;
        }

        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->dropColumn('nota_tecnico');
        });
    }
};
