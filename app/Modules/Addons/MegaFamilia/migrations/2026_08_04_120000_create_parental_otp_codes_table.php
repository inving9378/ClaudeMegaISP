<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos OTP (segundo factor) para la autenticación del padre en MegaFamilia.
 * Política aprobada por Irving (roadmap #496): TTL 5 min, máx 3 intentos,
 * bloqueo 15 min tras agotarlos. Solo guarda el hash del código, nunca el texto plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parental_otp_codes')) {
            return;
        }
        Schema::create('parental_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_main_information_id');
            $table->string('destino', 32);
            $table->string('canal', 16)->default('sms');
            $table->string('proposito', 32)->default('login');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->unsignedTinyInteger('max_intentos')->default(3);
            $table->timestamp('expira_at');
            $table->timestamp('bloqueado_hasta')->nullable();
            $table->timestamp('verificado_at')->nullable();
            $table->timestamps();

            $table->index(['client_main_information_id', 'proposito']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parental_otp_codes');
    }
};
