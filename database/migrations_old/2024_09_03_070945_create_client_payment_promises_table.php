<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('active_promise_payment')->default(false)->after('fecha_fin_periodo_gracia');
        });
        Schema::create('client_payment_promises', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->string('first_court_date')->nullable();
            $table->string('first_amount')->nullable();
            $table->boolean('first_amount_is_pay')->default(false);
            $table->string('second_court_date')->nullable();
            $table->string('second_amount')->nullable();
            $table->boolean('second_amount_is_pay')->default(false);
            $table->string('third_court_date')->nullable();
            $table->string('third_amount')->nullable();
            $table->boolean('third_amount_is_pay')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('active_promise_payment');
        });
        Schema::dropIfExists('client_payment_promises');
    }
};
