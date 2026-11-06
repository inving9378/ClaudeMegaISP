<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger inmutable: sin updates ni deletes desde la app; correcciones vía asiento de ajuste.
        Schema::create('inv_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('inv_portfolios')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('inv_orders')->nullOnDelete();
            $table->enum('tipo', [
                'deposito', 'retiro', 'compra', 'venta', 'dividendo', 'comision', 'impuesto', 'ajuste_fx',
            ]);
            $table->decimal('monto', 18, 4);
            $table->char('moneda', 3);
            $table->decimal('tipo_cambio', 18, 6)->default(1);
            $table->decimal('monto_moneda_base', 18, 4);
            $table->decimal('saldo_efectivo_resultante', 18, 4);
            $table->date('fecha');
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_transactions');
    }
};
