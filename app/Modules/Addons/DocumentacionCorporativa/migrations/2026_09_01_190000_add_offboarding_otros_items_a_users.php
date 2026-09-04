<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5d-2a (item roadmap #839) — checklist de los 6 ítems fijos de
 * offboarding SIN tabla propia (correo, VPN, WhatsApp, equipo, respaldo,
 * finiquito RH).
 *
 * Opción 1 aprobada por Irving para q1 de #839: persistir el estado como
 * columnas JSON "en la tabla de offboarding existente", sin tabla nueva. No
 * existe una tabla de offboarding por colaborador (`#815`, sin mergear, no
 * crea ninguna — su OffboardingController solo marca `revocado_at` en
 * `dc_inventario_accesos`/`dc_activos_digitales`, y anota al colaborador
 * saliente como `App\Models\User`). Esta columna cuelga de esa misma
 * entidad — es la tabla "existente" más fiel al criterio que Irving
 * rechazó explícitamente en la Opción 2 (no crear tabla nueva).
 *
 * Los 6 ítems fijos (clave→etiqueta) viven como constante en
 * `OffboardingOtrosItemsService`, no en catálogo de BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'offboarding_otros_items')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('offboarding_otros_items')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'offboarding_otros_items')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('offboarding_otros_items');
        });
    }
};
