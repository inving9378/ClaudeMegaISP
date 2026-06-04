<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add shift fields to talento_colaboradores (additive only)
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->after('base_salary');
            $table->time('shift_end')->nullable()->after('shift_start');
            $table->string('work_days', 20)->nullable()->after('shift_end'); // e.g. "1,2,3,4,5" (Mon=1..Sun=7)
        });

        Schema::create('talento_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->timestamp('check_in_at');
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedBigInteger('check_in_site_id')->nullable();
            $table->unsignedBigInteger('check_in_work_order_id')->nullable();
            $table->unsignedBigInteger('check_in_project_id')->nullable();
            $table->boolean('check_in_within_geofence')->default(false);
            $table->boolean('check_in_flagged')->default(false);
            $table->string('check_in_flag_reason', 120)->nullable();
            $table->timestamp('expected_end_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->enum('day_type', ['worked', 'no_work_company', 'absent'])->default('worked');
            $table->enum('status', ['open', 'closed', 'flagged'])->default('open');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('check_in_site_id')->references('id')->on('talento_work_sites')->onDelete('set null');
            $table->index(['colaborador_id', 'check_in_at']);
            $table->index(['status']);
            $table->index(['check_in_flagged']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_attendances');
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            $table->dropColumn(['shift_start', 'shift_end', 'work_days']);
        });
    }
};
