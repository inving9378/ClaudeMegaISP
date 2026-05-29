<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voip_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Principal');
            $table->string('sip_host');
            $table->unsignedSmallInteger('sip_port')->default(5060);
            $table->string('sip_username');
            $table->text('sip_secret');
            $table->string('sip_fromuser')->nullable();
            $table->string('sip_fromdomain')->nullable();
            $table->enum('estado', ['activa', 'inactiva', 'error'])->default('inactiva');
            $table->string('ultimo_registro_at')->nullable();
            $table->string('callerid_nombre')->default('Meganet Telecomunicaciones');
            $table->string('callerid_numero')->nullable();
            $table->boolean('activa')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_configuracion');
    }
};
