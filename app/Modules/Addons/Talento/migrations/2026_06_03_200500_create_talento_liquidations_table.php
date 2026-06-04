<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_liquidations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('total_units')->default(0);
            $table->decimal('base_paid', 10, 2)->default(0);
            $table->decimal('overproduction_paid', 10, 2)->default(0);
            $table->decimal('other_credits', 10, 2)->default(0);
            $table->decimal('other_debits', 10, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->enum('status', ['draft', 'closed'])->default('draft');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->unique(['colaborador_id', 'period_start', 'period_end'], 'talento_liq_colperiod_unique');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_liquidations');
    }
};
