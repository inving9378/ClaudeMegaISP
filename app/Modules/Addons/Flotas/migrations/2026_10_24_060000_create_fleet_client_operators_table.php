<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_client_operators')) {
            return;
        }

        Schema::create('fleet_client_operators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('licencia_numero')->nullable();
            $table->date('licencia_vigencia')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // No destructivo a propósito: pueden existir operadores reales de clientes.
    }
};
