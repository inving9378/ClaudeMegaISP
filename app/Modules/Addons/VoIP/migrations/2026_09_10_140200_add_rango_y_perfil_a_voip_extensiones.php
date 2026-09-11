<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga las extensiones a su rango y su perfil (item #9990718 §7 y §8).
 *
 * ADITIVA y todo nullable: las cinco extensiones que ya existen siguen funcionando
 * igual, sin rango ni perfil, hasta que el seeder o la UI las asocie. Ninguna se
 * renumera ni se toca — cambiarle el número a alguien que ya lo usa cuesta más de
 * lo que ordena.
 *
 * `voip_perfil_extension_id` es la SOBRESCRITURA, no la herencia normal: si está en
 * null, la extensión hereda el perfil de su rango, que es el caso corriente. Solo se
 * llena cuando una extensión concreta necesita apartarse de su departamento.
 *
 * `departamento` guarda el nombre con el que se siembra (`Técnicos de campo`), aparte
 * de `nombre`, que la UI reescribe cuando alguien toma la extensión. Sin esa columna,
 * renombrar "Técnicos de campo 03" a "Ana Ruiz" perdería el dato de a qué área
 * pertenece.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('voip_extensiones')) {
            return;
        }

        Schema::table('voip_extensiones', function (Blueprint $table) {
            if (! Schema::hasColumn('voip_extensiones', 'voip_rango_numeracion_id')) {
                $table->foreignId('voip_rango_numeracion_id')
                    ->nullable()
                    ->after('numero')
                    ->constrained('voip_rangos_numeracion')
                    // restrict: un rango con extensiones dentro no se borra.
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('voip_extensiones', 'voip_perfil_extension_id')) {
                $table->foreignId('voip_perfil_extension_id')
                    ->nullable()
                    ->after('voip_rango_numeracion_id')
                    ->constrained('voip_perfiles_extension')
                    ->nullOnDelete()   // si el perfil de sobrescritura se va, vuelve a heredar del rango
                    ->comment('Sobrescritura. NULL = hereda el perfil de su rango.');
            }

            if (! Schema::hasColumn('voip_extensiones', 'departamento')) {
                $table->string('departamento', 120)->nullable()->after('nombre');
            }

            if (! Schema::hasColumn('voip_extensiones', 'sembrada_por_sistema')) {
                $table->boolean('sembrada_por_sistema')->default(false)->after('activo')
                    ->comment('La creó el seeder de arranque, no una persona.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('voip_extensiones')) {
            return;
        }

        Schema::table('voip_extensiones', function (Blueprint $table) {
            foreach (['voip_rango_numeracion_id', 'voip_perfil_extension_id'] as $fk) {
                if (Schema::hasColumn('voip_extensiones', $fk)) {
                    $table->dropConstrainedForeignKey($fk);
                }
            }
            foreach (['departamento', 'sembrada_por_sistema'] as $col) {
                if (Schema::hasColumn('voip_extensiones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
