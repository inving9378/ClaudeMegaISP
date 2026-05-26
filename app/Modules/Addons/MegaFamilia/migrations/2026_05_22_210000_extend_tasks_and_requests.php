<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parental_tasks', function (Blueprint $t) {
            $t->date('due_date')->nullable()->after('reward_detail');
            $t->enum('priority', ['baja', 'media', 'alta'])->default('media')->after('due_date');
            $t->text('notes')->nullable()->after('photo_proof');
        });

        Schema::table('parental_requests', function (Blueprint $t) {
            $t->text('notes')->nullable()->after('expires_at');
        });

        Schema::table('parental_devices', function (Blueprint $t) {
            // Permitir el estado 'unlinked' que añadiremos vía unlink().
            $t->string('status', 32)->default('offline')->change();
        });
    }

    public function down(): void
    {
        Schema::table('parental_tasks', function (Blueprint $t) {
            $t->dropColumn(['due_date', 'priority', 'notes']);
        });
        Schema::table('parental_requests', function (Blueprint $t) {
            $t->dropColumn('notes');
        });
        Schema::table('parental_devices', function (Blueprint $t) {
            $t->enum('status', ['online', 'offline'])->default('offline')->change();
        });
    }
};
