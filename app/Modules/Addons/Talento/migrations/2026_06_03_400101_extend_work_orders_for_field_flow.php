<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_orders', function (Blueprint $table) {
            // Field flow additions
            $table->string('modem_sn', 100)->nullable()->after('olt_onu_id');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->after('modem_sn'); // resolved modem item
            $table->unsignedBigInteger('inventory_movement_id')->nullable()->after('inventory_item_id'); // custody transfer
            $table->timestamp('accepted_at')->nullable()->after('validated_at');
            $table->unsignedBigInteger('accepted_by')->nullable()->after('accepted_at');
            $table->timestamp('activation_requested_at')->nullable()->after('accepted_by');
            $table->timestamp('activation_confirmed_at')->nullable()->after('activation_requested_at');
            $table->unsignedBigInteger('activation_by')->nullable()->after('activation_confirmed_at');
            $table->boolean('survey_completed')->default(false)->after('activation_by');
        });

        // Extend status ENUM to include field-flow states
        // MySQL ALTER ENUM is additive
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE talento_work_orders
             MODIFY COLUMN status ENUM(
                 'pending','in_progress','completed','validated','cancelled',
                 'pending_activation','active','survey_pending'
             ) NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'modem_sn','inventory_item_id','inventory_movement_id',
                'accepted_at','accepted_by',
                'activation_requested_at','activation_confirmed_at','activation_by',
                'survey_completed',
            ]);
        });
    }
};
