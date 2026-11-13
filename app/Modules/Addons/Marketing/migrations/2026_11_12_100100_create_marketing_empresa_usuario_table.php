<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 0 — quién trabaja en cada empresa y con qué rol.
 *
 * El rol es POR EMPRESA (admin | marketing | ventas), no un rol Spatie: una persona puede ser
 * admin en una marca y ventas en otra. Quitar a alguien = activo = false (la fila se conserva).
 * created_by = null ⇒ la asignó la migración inicial 2026_11_12_100300.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_empresa_usuario')) {
            return;
        }

        Schema::create('marketing_empresa_usuario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'mkt_empresa_usuario_company_user_unique');
            $table->index(['user_id', 'activo'], 'mkt_empresa_usuario_user_activo_index');

            $table->foreign('company_id', 'mkt_empresa_usuario_company_fk')
                ->references('id')->on('marketing_empresas')->restrictOnDelete();
            $table->foreign('user_id', 'mkt_empresa_usuario_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_empresa_usuario');
    }
};
