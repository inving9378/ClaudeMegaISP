<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('talento_attendances', 'check_in_location_source')) {
                $table->string('check_in_location_source', 20)->default('gps')->after('check_in_lng');
            }
            if (! Schema::hasColumn('talento_attendances', 'check_out_location_source')) {
                $table->string('check_out_location_source', 20)->nullable()->after('check_out_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('talento_attendances', 'check_in_location_source')) {
                $table->dropColumn('check_in_location_source');
            }
            if (Schema::hasColumn('talento_attendances', 'check_out_location_source')) {
                $table->dropColumn('check_out_location_source');
            }
        });
    }
};
