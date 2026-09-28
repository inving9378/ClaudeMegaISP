<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía ia_proveedores.driver para aceptar 'claude_code' (nuevo adaptador
 * que usa el CLI Claude Code ya autenticado, en vez de una api_key medida —
 * ver ClaudeCodeAdaptador). Aditiva, no toca los valores existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ia_proveedores MODIFY COLUMN driver
            ENUM('claude','openai','gemini','openai_compatible','custom','claude_code') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE ia_proveedores SET driver='claude' WHERE driver='claude_code'");
        DB::statement("ALTER TABLE ia_proveedores MODIFY COLUMN driver
            ENUM('claude','openai','gemini','openai_compatible','custom') NOT NULL");
    }
};
