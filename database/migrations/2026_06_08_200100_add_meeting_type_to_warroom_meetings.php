<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warroom_meetings', function (Blueprint $table) {
            $table->enum('meeting_type', ['ordinaria', 'acta'])
                  ->default('ordinaria')
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('warroom_meetings', function (Blueprint $table) {
            $table->dropColumn('meeting_type');
        });
    }
};
