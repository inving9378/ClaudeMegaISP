<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_assets', function (Blueprint $table) {
            $table->id();
            $table->string('simbolo', 20)->unique();
            $table->string('nombre', 150);
            $table->enum('tipo', ['etf', 'accion', 'indice', 'fx']);
            $table->char('moneda', 3)->default('USD');
            $table->string('mercado', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_assets');
    }
};
