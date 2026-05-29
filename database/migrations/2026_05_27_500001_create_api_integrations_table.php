<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->default(1)->index();
            $table->string('provider', 50)->index();
            $table->string('slug', 100)->index();
            $table->string('name', 150);
            $table->text('encrypted_value')->nullable();
            $table->string('key_preview', 20)->nullable();
            $table->string('key_fingerprint', 64)->nullable();
            $table->json('config')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default_for_provider')->default(true);
            $table->string('last_validation_status', 20)->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integrations');
    }
};
