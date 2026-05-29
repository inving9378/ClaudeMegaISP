<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_channels', function (Blueprint $table) {
            $table->string('external_instance_id', 100)->nullable()->after('config')->index();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_channels', function (Blueprint $table) {
            $table->dropIndex(['external_instance_id']);
            $table->dropColumn('external_instance_id');
        });
    }
};
