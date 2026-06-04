<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('talento_roadmap_items')->where('status', 'in_progress')->update(['status' => 'backlog', 'updated_at' => $now]);
        DB::table('talento_roadmap_items')->where('phase', 3)->update(['status' => 'done', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('talento_roadmap_items')->where('phase', 3)->update(['status' => 'in_progress', 'updated_at' => now()]);
    }
};
