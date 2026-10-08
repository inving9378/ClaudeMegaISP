<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_module_connections')) {
            return; // idempotente
        }
        Schema::create('monitoring_module_connections', function (Blueprint $table) {
            $table->id();
            $table->string('consumer_module')->default('addon-monitoreo-oficial');
            $table->string('provider_module');   // core-mail | core-bell | addon-whatsapp-agent | addon-ia | ...
            $table->string('capability');         // notification.email.send | ai.document.analyze | ...
            $table->string('contract_version')->nullable();
            $table->string('status')->default('no_instalado'); // no_instalado|sin_config|sin_conexion|disponible|error
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();  // solo datos NO secretos
            $table->timestamps();

            $table->unique(['consumer_module', 'provider_module', 'capability'], 'monitoring_conn_unique');
        });
    }

    public function down(): void
    {
        // Tabla PROPIA del módulo: seguro eliminarla en la desinstalación.
        Schema::dropIfExists('monitoring_module_connections');
    }
};
