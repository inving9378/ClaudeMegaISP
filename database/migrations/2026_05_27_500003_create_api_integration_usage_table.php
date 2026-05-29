<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integration_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->index();
            $table->unsignedInteger('company_id')->default(1)->index();
            $table->date('usage_date')->index();
            $table->string('feature', 100);
            $table->unsignedInteger('call_count')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamps();

            $table->unique(['integration_id', 'usage_date', 'feature']);
            $table->foreign('integration_id')
                  ->references('id')->on('api_integrations')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integration_usage');
    }
};
