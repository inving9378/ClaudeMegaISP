<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de auditoría del backfill de users "espejo" huérfanos (item roadmap #105).
 *
 * Decisión de Irving (pregunta q3 del item, opción elegida): mysqldump de `users` +
 * tabla de mapping cliente→user_id creado, para poder hacer un rollback quirúrgico que
 * borre EXACTAMENTE los ids insertados en un batch, sin tocar nada más.
 *
 * `batch` agrupa cada corrida del comando (uuid), así conviven múltiples corridas
 * (dev, y más adelante prod) sin pisarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orphan_client_backfill_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch')->index();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id');
            $table->string('login_user');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orphan_client_backfill_log');
    }
};
