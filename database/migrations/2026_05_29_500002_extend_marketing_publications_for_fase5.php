<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing channel_id to legacy_channel_id to avoid FK conflict with new table
        Schema::table('marketing_publications', function (Blueprint $table) {
            // Add new columns for Fase 5 multichannel publisher
            $table->unsignedBigInteger('pub_channel_id')->nullable()->after('channel_id')
                ->comment('FK to marketing_publication_channels');
            $table->text('caption')->nullable()->after('custom_text');
            $table->json('hashtags')->nullable()->after('caption');
            $table->json('platform_options')->nullable()->after('hashtags');
            $table->timestamp('scheduled_for')->nullable()->after('scheduled_at');
            $table->string('external_post_url', 500)->nullable()->after('external_url');
            $table->integer('retry_count')->default(0)->after('failure_reason');
            $table->timestamp('next_retry_at')->nullable()->after('retry_count');
            $table->json('metrics')->nullable()->after('engagement');
            $table->timestamp('metrics_updated_at')->nullable()->after('metrics');
            $table->string('ab_variant_tag', 50)->nullable()->after('metrics_updated_at');
        });

        // Expand status enum to include new Fase 5 statuses
        DB::statement("ALTER TABLE marketing_publications MODIFY COLUMN status ENUM(
            'draft','queued','scheduled','publishing','published',
            'failed','waiting_credentials','cancelled',
            'pending','approved','rejected','sent'
        ) NOT NULL DEFAULT 'draft'");

        Schema::table('marketing_publications', function (Blueprint $table) {
            $table->index(['pub_channel_id', 'status'], 'idx_pub_channel_status');
            $table->index(['status', 'scheduled_for'], 'idx_pub_status_scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_publications', function (Blueprint $table) {
            $table->dropColumn([
                'pub_channel_id', 'caption', 'hashtags', 'platform_options',
                'scheduled_for', 'external_post_url', 'retry_count',
                'next_retry_at', 'metrics', 'metrics_updated_at', 'ab_variant_tag',
            ]);
            $table->dropIndex('idx_pub_channel_status');
            $table->dropIndex('idx_pub_status_scheduled');
        });

        DB::statement("ALTER TABLE marketing_publications MODIFY COLUMN status ENUM(
            'pending','approved','rejected','sent'
        ) NOT NULL DEFAULT 'pending'");
    }
};
