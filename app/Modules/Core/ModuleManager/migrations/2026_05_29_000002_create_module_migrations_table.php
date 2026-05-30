<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug');
            $table->string('migration');
            $table->timestamp('ran_at')->useCurrent();

            $table->index('module_slug');
            $table->unique(['module_slug', 'migration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_migrations');
    }
};
