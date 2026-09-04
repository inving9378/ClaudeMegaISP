<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #101 (Fase 5.5) — scaffold aditivo de push notifications para la futura
 * APK conductor. SOLO guarda el token del dispositivo; el envío real vía FCM
 * queda bloqueado hasta que el item #72 (Firebase greenfield) provea credenciales
 * reales (ver Services/Notifications/Drivers/PushChannel.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_driver_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 500)->unique();
            $table->enum('platform', ['android', 'ios'])->default('android');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_driver_push_tokens');
    }
};
