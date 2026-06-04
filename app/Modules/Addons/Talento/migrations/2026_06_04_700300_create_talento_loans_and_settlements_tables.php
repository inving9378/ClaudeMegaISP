<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Préstamos / adelantos
        Schema::create('talento_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->decimal('amount', 10, 2);               // monto original del préstamo
            $table->decimal('balance', 10, 2);              // saldo pendiente
            $table->decimal('repayment_weekly', 10, 2)->nullable(); // descuento semanal de nómina
            $table->text('reason')->nullable();
            // LFT: no se descuenta nada sin autorización por escrito
            $table->boolean('authorized')->default(false);
            $table->unsignedBigInteger('authorized_by')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->enum('status', ['active', 'paid'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['colaborador_id', 'status'], 'tl_col_status_idx');
        });

        // Sub-pasos 3-5: Finiquito
        Schema::create('talento_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->date('settlement_date');
            $table->enum('status', ['draft', 'closed'])->default('draft');
            $table->decimal('gross_credits', 10, 2)->default(0);   // a favor del colaborador
            $table->decimal('gross_debits', 10, 2)->default(0);    // en su contra
            $table->decimal('net_settlement', 10, 2)->default(0);  // créditos − débitos
            $table->json('detail')->nullable();                     // snapshot por concepto
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['colaborador_id', 'status'], 'ts_col_status_idx');
        });

        // Sub-paso 4: Items de material del finiquito (devuelto / dañado / faltante)
        Schema::create('talento_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_id');
            $table->unsignedBigInteger('stock_id');                 // inventory_item_stocks.id
            $table->unsignedBigInteger('inventory_item_id');
            $table->string('item_name', 200);                       // snapshot
            $table->decimal('unit_cost', 10, 2)->default(0);        // snapshot
            $table->decimal('current_stock', 10, 3);                // cantidad en custodia
            $table->enum('disposition', ['returned', 'damaged', 'missing'])->default('returned');
            $table->decimal('debit_amount', 10, 2)->default(0);     // cargo al finiquito (daño/falta)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('settlement_id')
                  ->references('id')->on('talento_settlements')->onDelete('cascade');
            $table->index(['settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_settlement_items');
        Schema::dropIfExists('talento_settlements');
        Schema::dropIfExists('talento_loans');
    }
};
