<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_config', function (Blueprint $table) {
            $table->id();
            // Ventana global de retención antes de enviar (nunca < 12 h)
            $table->unsignedSmallInteger('notification_window_hours')->default(12);
            // Si se envía la prefactura por correo al generarse (en_espera = sí, pero con ventana)
            $table->boolean('send_prefactura_email')->default(true);
            // El ticket NUNCA se envía por correo salvo esta bandera
            $table->boolean('send_ticket_email')->default(false);
            $table->timestamps();
        });

        // Registro único de configuración
        DB::table('billing_config')->insert([
            'notification_window_hours' => 12,
            'send_prefactura_email'     => true,
            'send_ticket_email'         => false,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_config');
    }
};
