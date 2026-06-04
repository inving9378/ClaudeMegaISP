<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_responsibility_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->unsignedBigInteger('source_work_order_id');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');   // starts_at + 6 months
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('source_work_order_id')->references('id')->on('talento_work_orders')->onDelete('restrict');
            $table->index(['client_id', 'active', 'expires_at'], 'trw_client_active_idx');
            $table->index(['colaborador_id', 'active'], 'trw_col_active_idx');
        });

        // Log of warranty reclassifications by supervisor
        Schema::create('talento_warranty_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('responsibility_window_id')->nullable();
            $table->string('original_type', 60);     // was: garantia_no_pagable
            $table->string('new_type', 60);           // overridden to: garantia_pagable
            $table->text('reason');
            $table->string('evidence_ref', 255)->nullable();
            $table->unsignedBigInteger('overridden_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_warranty_overrides');
        Schema::dropIfExists('talento_responsibility_windows');
    }
};
