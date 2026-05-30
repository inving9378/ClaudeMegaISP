<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warroom_action_items', function (Blueprint $table) {
            $table->index('linked_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('warroom_action_items', function (Blueprint $table) {
            $table->dropIndex(['linked_task_id']);
        });
    }
};
