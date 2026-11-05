<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_client_alert_contacts')) {
            Schema::create('fleet_client_alert_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->string('nombre', 120);
                $table->string('email', 190)->nullable();
                $table->string('telefono', 30)->nullable();
                $table->boolean('es_principal')->default(false);
                $table->json('channels')->nullable();       // [portal,email,whatsapp]
                $table->json('tipos_alerta')->nullable();   // null = todos
                $table->time('silencio_desde')->nullable(); // horario silencioso opcional
                $table->time('silencio_hasta')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('fleet_client_alert_deliveries')) {
            Schema::create('fleet_client_alert_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('alert_id')->nullable()->index(); // fleet_client_alerts
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->string('channel', 20);
                $table->string('destination', 190)->nullable();
                $table->string('status', 20);                // sent|failed|skipped|queued
                $table->string('motivo', 190)->nullable();   // tope, silencio, anti-repeticion, error
                $table->timestamp('created_at')->nullable();

                $table->index(['client_id', 'channel', 'status', 'created_at'], 'fleet_cad_cuota');
                $table->index(['rule_id', 'vehicle_id', 'contact_id', 'created_at'], 'fleet_cad_antirepeticion');
            });
        }

        if (! Schema::hasTable('fleet_client_whatsapp_usage')) {
            Schema::create('fleet_client_whatsapp_usage', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->char('periodo', 7);                  // YYYY-MM
                $table->unsignedInteger('enviados')->default(0);
                $table->timestamp('aviso_tope_at')->nullable(); // aviso "llegaste al tope" una sola vez
                $table->timestamps();

                $table->unique(['client_id', 'periodo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_client_whatsapp_usage');
        Schema::dropIfExists('fleet_client_alert_deliveries');
        Schema::dropIfExists('fleet_client_alert_contacts');
    }
};
