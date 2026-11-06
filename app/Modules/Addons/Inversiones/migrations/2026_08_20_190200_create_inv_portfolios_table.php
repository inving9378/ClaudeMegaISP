<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->enum('modo', ['paper', 'live'])->default('paper');
            $table->enum('tipo_motor', ['nucleo', 'satelite']);
            $table->char('moneda_base', 3)->default('USD');
            $table->decimal('capital_inicial', 18, 4);
            $table->decimal('efectivo', 18, 4);
            $table->tinyInteger('perfil_agresividad')->default(1);
            $table->enum('estado', ['activo', 'pausado', 'detenido_riesgo', 'archivado'])->default('activo');
            $table->string('motivo_pausa', 255)->nullable();
            $table->dateTime('pausado_hasta')->nullable();
            $table->boolean('requiere_rearme_manual')->default(false);
            $table->string('broker', 30)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_portfolios');
    }
};
