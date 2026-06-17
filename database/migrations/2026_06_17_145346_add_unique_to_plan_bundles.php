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
        Schema::table('plan_bundles', function (Blueprint $table) {
            $table->unique(
                ['bundle_id', 'plan_bundle_id', 'plan_bundle_type'],
                'plan_bundles_bundle_plan_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('plan_bundles', function (Blueprint $table) {
            $table->dropUnique('plan_bundles_bundle_plan_type_unique');
        });
    }
};
