<?php

use App\Models\GeneralConfigurationRule;
use Carbon\Carbon;
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
        Schema::create('general_configuration_rule', function (Blueprint $table) {
            $table->id();
            $table->decimal('installation_cost', $precision = 8, $scale = 2)->default(0);
            $table->decimal('iva', $precision = 8, $scale = 2)->default(0);
            $table->timestamps();
        });

        Schema::create('history_general_configuration_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')->references('id')->on('general_configuration_rule')->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
        });

        GeneralConfigurationRule::create([
            'iva' => 16,
            'installation_cost' => 1500,
            'created_at' => Carbon::createFromFormat('Y-m-d', '2024-06-01'),
            'updated_at' => Carbon::createFromFormat('Y-m-d', '2024-06-01')
        ]);

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('discount', $precision = 8, $scale = 2)->default(0);
            $table->string('invoice_number');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('discounts_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discount_id');
            $table->foreign('discount_id')->references('id')->on('discounts')->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')->references('id')->on('history_general_configuration_rule')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->foreign('sale_id')->references('id')->on('client_main_information')->cascadeOnDelete();
            $table->decimal('discount', $precision = 8, $scale = 2)->default(0);
            $table->string('type');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts_sales');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('history_general_configuration_rule');
        Schema::dropIfExists('general_configuration_rule');
    }
};
