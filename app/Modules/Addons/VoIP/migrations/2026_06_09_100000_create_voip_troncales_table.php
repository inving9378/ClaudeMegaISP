<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voip_troncales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('proveedor', 100)->nullable();
            $table->enum('tipo', ['registro', 'ip']);
            $table->enum('direccion', ['entrante', 'saliente', 'ambas']);
            $table->string('host', 255);
            $table->unsignedSmallInteger('puerto')->default(5060);
            $table->string('usuario', 100)->nullable();
            $table->text('secret')->nullable();
            $table->string('contexto', 100)->default('from-trunk');
            $table->string('did', 50)->nullable();
            $table->string('codecs', 100)->default('ulaw,alaw');
            $table->string('transporte', 100)->default('transport-udp');
            $table->boolean('activo')->default(true);
            $table->timestamp('provisionado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_troncales');
    }
};
