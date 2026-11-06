<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_risk_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('inv_portfolios')->cascadeOnDelete();
            $table->enum('tipo', [
                'limite_diario', 'limite_semanal', 'limite_mensual', 'drawdown_maximo',
                'stop_loss', 'concentracion', 'efectivo_minimo', 'orden_rechazada', 'objetivo_ganancia',
            ]);
            $table->enum('severidad', ['info', 'advertencia', 'critico'])->default('info');
            $table->text('descripcion')->nullable();
            $table->decimal('valor_observado', 18, 4)->nullable();
            $table->decimal('valor_limite', 18, 4)->nullable();
            $table->string('accion_tomada', 120)->nullable();
            $table->boolean('resuelto')->default(false);
            $table->dateTime('resuelto_en')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'severidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_risk_events');
    }
};
