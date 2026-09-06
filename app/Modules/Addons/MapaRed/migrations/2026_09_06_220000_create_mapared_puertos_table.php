<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-10 — Entidad puerto polimórfica (aditiva, item roadmap #946).
 *
 * `mapared_puertos` es el modelo NUEVO y limpio de puerto (independiente de las tablas
 * espejo `mapared_devices_ports`/`mapared_ports` de MR-04, que son solo la copia 1:1 del
 * sistema legado para la migración de datos de MR-05/MR-15). Cualquier elemento de la red
 * (OLT, splitter, NAP, ODF, ONT, cable...) puede ser dueño de puertos vía `puertable_type`/
 * `puertable_id`.
 *
 * `frame`/`slot` son nullable y solo se usan para el rol `pon` (notación OLT Frame/Slot/Port
 * tipo "0/3/2", ver HuaweiDriver); el resto de roles solo usa `numero`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_puertos', function (Blueprint $table) {
            $table->id();
            $table->string('puertable_type');
            $table->unsignedBigInteger('puertable_id');
            $table->string('numero')->nullable();
            $table->unsignedSmallInteger('frame')->nullable();
            $table->unsignedSmallInteger('slot')->nullable();
            $table->enum('rol', ['pon', 'splitter_in', 'splitter_out', 'nap_salida', 'odf', 'ont']);
            $table->enum('estado', ['libre', 'ocupado', 'reservado', 'dañado'])->default('libre');
            $table->string('etiqueta')->nullable();
            $table->timestamps();

            $table->index(['puertable_type', 'puertable_id'], 'mapared_puertos_puertable_index');
            $table->index('estado');
            $table->unique(
                ['puertable_type', 'puertable_id', 'rol', 'numero'],
                'mapared_puertos_owner_rol_numero_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_puertos');
    }
};
