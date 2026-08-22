<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1016 — cursor de procesamiento incremental del minero de bitácora.
 * Una fila por `fuente` (v1: 'activity_log') con el último id ya evaluado, para
 * que la corrida cada 15 min (q4 de #1016) solo procese lo nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_minero_cursores', function (Blueprint $table) {
            $table->id();
            $table->string('fuente', 64)->unique();
            $table->unsignedBigInteger('ultimo_id')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_minero_cursores');
    }
};
