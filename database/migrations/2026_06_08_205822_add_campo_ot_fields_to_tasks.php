<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Capa 3 — campos extra que las OTs de campo necesitan en tasks
// nota_tecnico: nota libre del técnico (misma columna que talento_work_orders)
// olt_onu_id / caja_id / modem_sn: datos de red para OTs de campo (Capa 4 los escribirá)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('nota_tecnico')->nullable()->after('description');
            $table->unsignedBigInteger('olt_onu_id')->nullable()->after('nota_tecnico');
            $table->unsignedBigInteger('caja_id')->nullable()->after('olt_onu_id');
            $table->string('modem_sn', 100)->nullable()->after('caja_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['nota_tecnico', 'olt_onu_id', 'caja_id', 'modem_sn']);
        });
    }
};
