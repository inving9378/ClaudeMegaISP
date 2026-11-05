<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marca (sin borrar) las órdenes antiguas de cuentas demo. Aditiva; null/0 = orden real.
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('talento_work_orders', 'es_prueba')) {
            Schema::table('talento_work_orders', function (Blueprint $table) {
                $table->boolean('es_prueba')->default(false)->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talento_work_orders', 'es_prueba')) {
            Schema::table('talento_work_orders', function (Blueprint $table) {
                $table->dropColumn('es_prueba');
            });
        }
    }
};
