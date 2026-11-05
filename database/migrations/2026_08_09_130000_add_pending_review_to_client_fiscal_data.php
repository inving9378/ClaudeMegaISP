<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #492 (q2, Opción 3 elegida por Irving): la edición de datos
 * fiscales desde la app MegaFamilia del padre es self-service pero NO se
 * aplica de inmediato — queda "pendiente de aprobación" hasta que oficina
 * valide (un RFC mal capturado puede romper el timbrado). Estas columnas
 * guardan esa solicitud aparte de los campos autoritativos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_fiscal_data', function (Blueprint $table) {
            if (! Schema::hasColumn('client_fiscal_data', 'pending_payload')) {
                $table->json('pending_payload')->nullable()->after('constancia_path');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_status')) {
                $table->string('pending_status', 20)->nullable()->after('pending_payload');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_requested_at')) {
                $table->timestamp('pending_requested_at')->nullable()->after('pending_status');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_requested_by')) {
                $table->unsignedBigInteger('pending_requested_by')->nullable()->after('pending_requested_at');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_reviewed_by')) {
                $table->unsignedBigInteger('pending_reviewed_by')->nullable()->after('pending_requested_by');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_reviewed_at')) {
                $table->timestamp('pending_reviewed_at')->nullable()->after('pending_reviewed_by');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'pending_reject_reason')) {
                $table->string('pending_reject_reason', 500)->nullable()->after('pending_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_fiscal_data', function (Blueprint $table) {
            $table->dropColumn([
                'pending_payload',
                'pending_status',
                'pending_requested_at',
                'pending_requested_by',
                'pending_reviewed_by',
                'pending_reviewed_at',
                'pending_reject_reason',
            ]);
        });
    }
};
