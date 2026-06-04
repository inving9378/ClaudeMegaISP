<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->enum('type', ['credit', 'debit']);
            $table->string('concept', 60);  // salary_base, overproduction, bonus, penalty, advance, fund, adjustment, embajador, …
            $table->decimal('amount', 10, 2);
            $table->string('reference_type', 100)->nullable(); // polymorphic
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // NO updated_at, NO deleted_at — ledger is immutable

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['colaborador_id', 'period_start', 'period_end'], 'tle_col_period_idx');
            $table->index(['concept']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_ledger_entries');
    }
};
