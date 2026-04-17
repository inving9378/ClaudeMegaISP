<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            "UPDATE map_devices AS child
            JOIN map_devices AS parent ON child.parent_id = parent.id
            SET child.layer_id = parent.layer_id
            WHERE child.layer_id IS NULL 
            AND child.parent_id IS NOT NULL 
            AND parent.layer_id IS NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
