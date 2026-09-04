<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Infra transversal (item #627): registro de push tokens FCM por usuario.
 * UNIQUE en token (no en user_id) para soportar multi-dispositivo por
 * usuario y upsert por token en el registro (decisión aprobada por Irving).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 20)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void { Schema::dropIfExists('push_tokens'); }
};
