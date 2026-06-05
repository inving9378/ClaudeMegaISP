<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_evidence_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('permite_varias')->default(false);
            $table->boolean('requiere_justificacion')->default(false);
            $table->boolean('es_lectura_dbm')->default(false);
            $table->boolean('es_firma')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_evidence_types');
    }
};
