<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_price_bars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('inv_assets')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('open', 18, 6);
            $table->decimal('high', 18, 6);
            $table->decimal('low', 18, 6);
            $table->decimal('close', 18, 6);
            $table->decimal('adj_close', 18, 6);
            $table->bigInteger('volumen')->default(0);
            $table->string('fuente', 30)->default('manual');
            $table->timestamps();

            $table->unique(['asset_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_price_bars');
    }
};
