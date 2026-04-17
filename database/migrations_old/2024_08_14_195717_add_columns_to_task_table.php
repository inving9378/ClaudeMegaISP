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
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('start_time')->nullable()->after('start_date');
            $table->string('end_time')->nullable()->after('start_time');
            $table->string('time_to_task_location')->nullable()->after('start_date');
            $table->string('time_from_task_location')->nullable()->after('time_to_task_location');
            $table->string('priority')->nullable()->after('time_from_task_location');
            $table->string('template_verification')->nullable()->after('priority');
            $table->longText('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('start_time');
            $table->dropColumn('end_time');
            $table->dropColumn('time_to_task_location');
            $table->dropColumn('time_from_task_location');
            $table->dropColumn('priority');
            $table->dropColumn('template_verification');
            $table->longText('description')->nullable()->change();
        });
    }
};
