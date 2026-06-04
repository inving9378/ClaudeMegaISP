<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('talento_roadmap_items')->where('status', 'in_progress')->update(['status' => 'backlog', 'updated_at' => $now]);
        DB::table('talento_roadmap_items')->where('phase', 2)->update(['status' => 'done', 'updated_at' => $now]);
        // Fase 3 queda como próxima in_progress (no la marcamos todavía — se hace al iniciar Fase 3)
    }

    public function down(): void
    {
        DB::table('talento_roadmap_items')->where('phase', 2)->update(['status' => 'in_progress', 'updated_at' => now()]);
    }
};
