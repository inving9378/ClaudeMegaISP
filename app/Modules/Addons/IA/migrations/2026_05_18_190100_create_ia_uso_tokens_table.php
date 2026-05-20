<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ia_uso_tokens')) {
            return;
        }

        Schema::create('ia_uso_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ia_conversacion_id')->nullable()->constrained('ia_conversaciones')->nullOnDelete();
            $table->foreignId('ia_mensaje_id')->nullable()->constrained('ia_mensajes')->nullOnDelete();
            $table->foreignId('ia_proveedor_id')->nullable()->constrained('ia_proveedores')->nullOnDelete();
            $table->string('proveedor', 50);
            $table->string('modelo', 100);
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->unsignedInteger('tokens_total')->default(0);
            $table->decimal('costo_estimado', 10, 6)->default(0);
            $table->date('fecha');
            $table->string('origen', 20)->default('ia');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'fecha']);
            $table->index(['proveedor', 'modelo']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_uso_tokens');
    }
};
