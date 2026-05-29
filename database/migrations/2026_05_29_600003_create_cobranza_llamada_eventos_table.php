<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranza_llamada_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llamada_id')->constrained('cobranza_llamadas')->cascadeOnDelete();
            $table->enum('evento', [
                'Originate', 'ANSWER', 'BUSY', 'NOANSWER',
                'FAILED', 'DTMF', 'TRANSFER', 'HANGUP',
            ]);
            $table->json('payload')->nullable();
            $table->timestamp('ocurrido_at')->useCurrent();

            $table->index(['llamada_id', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_llamada_eventos');
    }
};
