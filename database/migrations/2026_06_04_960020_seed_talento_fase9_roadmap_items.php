<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registra 4 items de seguimiento de la Fase 8-9 de Talento.
 * Idempotente: no inserta si el título ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roadmap_items')) {
            return;
        }

        $items = [
            [
                'match'       => '%compensación%roles%técnicos%Talento%',
                'title'       => '[DECISIÓN] Talento: definir reglas de compensación de roles no-técnicos',
                'description' => 'contabilidad/mostrador/atención a clientes no tienen reglas de compensación. '
                    . 'El motor de OTs acepta cualquier tipo de colaborador pero los montos y fórmulas quedan vacíos. '
                    . 'BLOQUEA cerrar la Fase 9. Requiere definición de negocio (Irving) antes de implementar.',
                'status'   => 'pending',
                'priority' => 'alta',
            ],
            [
                'match'       => '%window.Chart%Talento%',
                'title'       => 'Talento: dashboard exigía window.Chart global (gráfica con fallback silencioso) — RESUELTO',
                'description' => 'TalentoDashboard.vue dependía de window.Chart para la gráfica de producción diaria. '
                    . 'Si Chart.js no estaba disponible globalmente la gráfica no renderizaba sin error visible. '
                    . 'Resuelto importando Chart directamente desde chart.js/auto dentro del componente.',
                'status'   => 'done',
                'priority' => 'alta',
            ],
            [
                'match'       => '%CommissionRule%TransactionSeller%Talento%',
                'title'       => '[DEUDA TÉCNICA] Talento: sin bridge entre CommissionRule/TransactionSeller (vendedores) y el motor',
                'description' => 'El sistema de comisiones de vendedores (CommissionRule / TransactionSeller) corre en paralelo '
                    . 'al motor Talento. No está duplicado pero no hay puente entre ambos. '
                    . 'Decidir: migrar al motor único o construir bridge de sincronización.',
                'status'   => 'pending',
                'priority' => 'media',
            ],
            [
                'match'       => '%talento%.view%17 roles%',
                'title'       => '[REVISIÓN] Talento: revisar alcance de permisos talento.*.view (asignados a 17 roles)',
                'description' => 'Los permisos talento.dashboard.view / escalafon.view / embajadores.view se asignaron a TODOS '
                    . 'los roles existentes (17) en la migración de Fase 8. Solo talento.escalafon.manage quedó restringido '
                    . 'a super-administrator + DESARROLLADOR. Confirmar si el alcance amplio es intencional o si conviene '
                    . 'restringir a roles técnicos. NO modificar permisos hasta decidir.',
                'status'   => 'pending',
                'priority' => 'baja',
            ],
        ];

        foreach ($items as $item) {
            if (DB::table('roadmap_items')->where('title', 'like', $item['match'])->exists()) {
                continue;
            }
            DB::table('roadmap_items')->insert([
                'title'       => $item['title'],
                'description' => $item['description'],
                'status'      => $item['status'],
                'priority'    => $item['priority'],
                'subtasks'    => '[]',
                'log'         => '[]',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void {}
};
