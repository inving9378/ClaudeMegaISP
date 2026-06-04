<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        // Enforce single in_progress: reset all to backlog first
        DB::table('talento_roadmap_items')->where('status', 'in_progress')->update(['status' => 'backlog', 'updated_at' => $now]);
        // Fase 1 → done
        DB::table('talento_roadmap_items')->where('phase', 1)->update(['status' => 'done', 'updated_at' => $now]);
        // Fase 2 → in_progress
        DB::table('talento_roadmap_items')->where('phase', 2)->update(['status' => 'in_progress', 'updated_at' => $now]);
    }

    public function down(): void
    {
        $now = now();
        DB::table('talento_roadmap_items')->where('phase', 2)->update(['status' => 'backlog', 'updated_at' => $now]);
        DB::table('talento_roadmap_items')->where('phase', 1)->update(['status' => 'in_progress', 'updated_at' => $now]);
    }
};
