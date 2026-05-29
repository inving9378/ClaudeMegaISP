<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranza_campanas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('estado', ['borrador', 'activa', 'pausada', 'completada'])->default('borrador');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->time('hora_inicio')->default('09:00:00');
            $table->time('hora_fin')->default('20:00:00');
            $table->unsignedTinyInteger('max_intentos')->default(3);
            $table->unsignedSmallInteger('minutos_entre_intentos')->default(180);
            $table->string('audio_mensaje')->nullable();
            $table->unsignedInteger('dias_vencimiento')->default(1);
            $table->text('notas')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_campanas');
    }
};
