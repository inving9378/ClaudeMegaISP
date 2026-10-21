<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Retira el catálogo de puestos (item #923) y el texto libre job_title: el puesto de cada
 * persona es su rol en Administración. Nadie tenía puesto (0 de 35 en dev, copia de prod del
 * 15-oct) y el único uso era un paquete de documentos para "Vendedor".
 *
 * Los paquetes de documentos quedan por rol: la columna `puesto` de
 * talento_puesto_document_templates pasa a guardar el nombre del rol. Decisión del 2026-10-21;
 * respaldo de las filas en la bitácora de ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_puesto_document_templates')) {
            $this->paquetesARoles();

            if (Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
                Schema::table('talento_puesto_document_templates', function (Blueprint $t) {
                    $t->dropForeign(['puesto_id']);
                    // Sin quitar antes el único compuesto, MySQL lo dejaría como único sobre template_id.
                    $t->dropUnique('tpdt_puesto_id_template_unique');
                    $t->dropIndex(['puesto_id']);
                    $t->dropColumn('puesto_id');
                });
            }
        }

        if (Schema::hasColumn('talento_colaboradores', 'puesto_id')) {
            Schema::table('talento_colaboradores', function (Blueprint $t) {
                $t->dropForeign(['puesto_id']);
                $t->dropColumn('puesto_id');
            });
        }
        if (Schema::hasColumn('talento_colaboradores', 'job_title')) {
            Schema::table('talento_colaboradores', fn (Blueprint $t) => $t->dropColumn('job_title'));
        }

        Schema::dropIfExists('talento_puestos');

        DB::table('permissions')->where('name', 'talento.puestos.manage')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Cambia el nombre del puesto del catálogo por el rol que le corresponde ("Técnico" → TECNICO). */
    private function paquetesARoles(): void
    {
        $normal = fn (string $s) => Str::lower(Str::ascii($s));
        $roles = DB::table('roles')->pluck('name')->mapWithKeys(fn ($r) => [$normal($r) => $r]);

        foreach (DB::table('talento_puesto_document_templates')->get() as $fila) {
            $rol = $roles[$normal((string) $fila->puesto)] ?? null;
            if (!$rol || $rol === $fila->puesto) {
                continue;
            }
            $yaExiste = DB::table('talento_puesto_document_templates')
                ->where('puesto', $rol)->where('template_id', $fila->template_id)->exists();
            $yaExiste
                ? DB::table('talento_puesto_document_templates')->where('id', $fila->id)->delete()
                : DB::table('talento_puesto_document_templates')->where('id', $fila->id)->update(['puesto' => $rol]);
        }
    }

    public function down(): void
    {
        // Solo la estructura; las filas viven en el respaldo de la bitácora del 2026-10-21.
        if (!Schema::hasTable('talento_puestos')) {
            Schema::create('talento_puestos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasColumn('talento_colaboradores', 'job_title')) {
            Schema::table('talento_colaboradores', fn (Blueprint $t) => $t->string('job_title', 100)->nullable()->after('emergency_contact_phone'));
        }
        if (!Schema::hasColumn('talento_colaboradores', 'puesto_id')) {
            Schema::table('talento_colaboradores', function (Blueprint $t) {
                $t->unsignedBigInteger('puesto_id')->nullable()->after('job_title');
                $t->foreign('puesto_id')->references('id')->on('talento_puestos')->nullOnDelete();
            });
        }
        if (Schema::hasTable('talento_puesto_document_templates') && !Schema::hasColumn('talento_puesto_document_templates', 'puesto_id')) {
            Schema::table('talento_puesto_document_templates', function (Blueprint $t) {
                $t->unsignedBigInteger('puesto_id')->nullable()->after('puesto');
                $t->index('puesto_id');
                $t->unique(['puesto_id', 'template_id'], 'tpdt_puesto_id_template_unique');
                $t->foreign('puesto_id')->references('id')->on('talento_puestos')->cascadeOnDelete();
            });
        }
        DB::table('permissions')->insertOrIgnore(['name' => 'talento.puestos.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
