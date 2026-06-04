<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_work_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('type', ['bodega', 'oficina', 'predio', 'otro'])->default('oficina');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(200);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['active']);
        });

        // Global config: default check-in radius (meters)
        // We store it in a simple key-value if the table exists, else skip
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'talento_checkin_default_radius_m'],
                ['value' => '300', 'description' => 'Radio por defecto para validación de check-in (metros)', 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_sites');
    }
};
