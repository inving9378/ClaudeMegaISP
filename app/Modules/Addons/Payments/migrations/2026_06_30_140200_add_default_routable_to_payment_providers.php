<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distingue el motor PRINCIPAL del secundario y si el proveedor enruta dinero.
     *
     *   is_default  → el motor de cobro nativo (conciliación por referencia) es el default.
     *   is_routable → el proveedor mueve dinero por su cuenta (OpenPay sí; el nativo NO:
     *                 el cliente transfiere a las cuentas de Meganet, no a una CLABE enrutable).
     *
     * Aditiva: solo agrega columnas, no toca datos existentes.
     */
    public function up(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->boolean('is_routable')->default(true)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_routable']);
        });
    }
};
