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
        // Aditiva — roadmap #748 (Fase 3a de la unificación invoices/client_invoices, #632).
        // needs_review/review_reason marcan discrepancias del backfill sin pisar nada (q2 de #722).
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('type');
            $table->text('review_reason')->nullable()->after('needs_review');
        });

        // client_invoice_id hoy tiene índice simple (migración 2026_08_28_192630 de #721, ya
        // corrida en esta BD aunque esa rama no esté mergeada a main). 0 filas pobladas → seguro
        // subirlo a UNIQUE, y sirve de candado real de idempotencia para el backfill de Fase 3b.
        //
        // Guard (#854): la columna client_invoice_id la CREA esa misma migración de la rama
        // #721 (circuito/item-721-…, commit 06efaf49), que no está mergeada a main. En la
        // reconstrucción aislada de schema:build-reference (solo main) la columna no existe
        // todavía, así que ni el dropIndex ni el unique() son alcanzables — sin este guard,
        // ambos truenan. Sin efecto en dev/prod real (ahí la columna y su índice simple ya
        // existen porque #721 ya corrió contra esta BD).
        if (Schema::hasColumn('invoices', 'client_invoice_id')) {
            $indexSimpleExiste = collect(DB::select(
                "SHOW INDEX FROM invoices WHERE Key_name = 'invoices_client_invoice_id_index'"
            ))->isNotEmpty();

            if ($indexSimpleExiste) {
                Schema::table('invoices', function (Blueprint $table) {
                    $table->dropIndex(['client_invoice_id']);
                });
            }

            Schema::table('invoices', function (Blueprint $table) {
                $table->unique('client_invoice_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['client_invoice_id']);
            $table->index('client_invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['needs_review', 'review_reason']);
        });
    }
};
