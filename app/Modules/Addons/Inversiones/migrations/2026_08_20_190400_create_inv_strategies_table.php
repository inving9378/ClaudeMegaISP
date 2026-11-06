<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('slug', 100)->unique();
            $table->enum('motor', ['nucleo', 'satelite']);
            $table->text('descripcion')->nullable();
            $table->json('parametros')->nullable();
            $table->enum('estado', ['borrador', 'backtest', 'paper', 'retirada'])->default('borrador');
            $table->string('codigo_python', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_strategies');
    }
};
