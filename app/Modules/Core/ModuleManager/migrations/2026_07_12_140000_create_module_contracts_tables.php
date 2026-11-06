<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de contratos de API inter-módulos (item roadmap #308, Fase 3 recortada).
 * Un "contrato" es un servicio/evento/trait compartido entre módulos (no rutas HTTP —
 * el acoplamiento real en este repo es por clase compartida, según auditoría Fase 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('modulo_proveedor');
            $table->enum('tipo', ['servicio', 'evento', 'trait']);
            $table->string('nombre');
            $table->string('version', 20)->default('1.0.0');
            $table->enum('estado', ['activa', 'deprecada', 'sunset'])->default('activa');
            $table->string('dueno_designado')->nullable();
            $table->json('contrato')->nullable();
            $table->timestamp('fecha_estado')->useCurrent();
            $table->timestamps();

            $table->unique(['modulo_proveedor', 'tipo', 'nombre']);
        });

        Schema::create('module_contract_consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('module_contracts')->cascadeOnDelete();
            $table->string('modulo_consumidor');
            $table->string('tipo_uso')->nullable();
            $table->enum('estado_migracion', ['al_dia', 'pendiente', 'migrado'])->default('al_dia');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'modulo_consumidor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_contract_consumers');
        Schema::dropIfExists('module_contracts');
    }
};
