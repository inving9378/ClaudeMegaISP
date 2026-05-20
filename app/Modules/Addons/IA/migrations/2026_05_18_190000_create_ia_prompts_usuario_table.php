<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ia_prompts_usuario')) {
            return;
        }

        Schema::create('ia_prompts_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 250);
            $table->text('contenido');
            $table->string('categoria', 100)->nullable();
            $table->boolean('es_publico')->default(false);
            $table->unsignedInteger('usos')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('categoria');
            $table->index('es_publico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_prompts_usuario');
    }
};
