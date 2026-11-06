<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #473 — recuperar contraseña del Portal Cliente con código por WhatsApp.
 *
 * Una fila = un código solicitado. otp_hash y reset_token_hash guardan SOLO el hash (bcrypt).
 * Vigencias en DATETIME: un TIMESTAMP NOT NULL sin default se vuelve ON UPDATE CURRENT_TIMESTAMP
 * en servidores con explicit_defaults_for_timestamp=OFF, y cada intento fallido movería la caducidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_password_otps')) {
            return;
        }

        Schema::create('portal_password_otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('otp_hash', 255);
            $table->string('canal', 20)->default('whatsapp');
            $table->string('destino_enmascarado', 60)->nullable();
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->string('reset_token_hash', 255)->nullable();
            $table->dateTime('reset_token_expires_at')->nullable();
            $table->dateTime('reset_token_used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_otps');
    }
};
