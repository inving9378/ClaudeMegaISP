<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('parental_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('school_level', ['primaria', 'secundaria', 'preparatoria'])->nullable();
            $table->enum('profile_type', ['nino', 'preadolescente', 'adolescente'])->default('nino');
            $table->string('photo')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_profiles'); }
};
