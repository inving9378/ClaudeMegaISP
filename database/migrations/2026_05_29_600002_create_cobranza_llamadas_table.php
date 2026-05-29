<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranza_llamadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('cobranza_campanas')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('telefono', 20);
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->enum('estado', [
                'pendiente', 'marcando', 'contestada', 'no_contesto',
                'ocupado', 'fallida', 'pagada', 'excluida',
            ])->default('pendiente');
            $table->timestamp('ultimo_intento_at')->nullable();
            $table->timestamp('proximo_intento_at')->nullable();
            $table->string('ami_channel', 100)->nullable();
            $table->string('ami_uniqueid', 50)->nullable();
            $table->decimal('monto_vencido', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['campana_id', 'estado', 'proximo_intento_at']);
            $table->index(['client_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_llamadas');
    }
};
