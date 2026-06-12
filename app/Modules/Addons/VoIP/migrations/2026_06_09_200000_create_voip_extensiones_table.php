<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voip_extensiones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('nombre', 100);
            $table->text('secret');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->enum('tipo_dispositivo', ['telefono_ip', 'softphone'])->default('softphone');
            $table->string('contexto', 100)->default('from-internal');
            $table->string('codecs', 100)->default('ulaw,alaw');
            $table->string('transporte', 100)->default('transport-udp');
            $table->string('callerid', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('provisionado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_extensiones');
    }
};
