<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->index();
            $table->unsignedInteger('company_id')->default(1)->index();
            $table->string('event_type', 50);
            $table->string('actor', 150)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('integration_id')
                  ->references('id')->on('api_integrations')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integration_logs');
    }
};
