<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #595 (F1 de consistencia eventual Mikrotik): bitácora de intentos de
 * sincronización. Aditiva; las columnas mikrotik_sync_* de los servicios ya
 * existen (item #86).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mikrotik_sync_attempts')) {
            return;
        }
        Schema::create('mikrotik_sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->unsignedBigInteger('service_id');
            $table->enum('resultado', ['ok', 'error']);
            $table->text('razon')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['service_type', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_sync_attempts');
    }
};
