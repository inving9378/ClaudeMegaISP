<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $phases = [
        [0, 'Infraestructura base',     'Módulo skeleton, permisos talento.*, sidebar, 3 tablas (colaboradores, devices, roadmap), catálogo de roles canónico.',                             'done',        '2026-06-03'],
        [1, 'Identidad del colaborador','CRUD admin colaboradores, vista custodia (solo lectura sobre Inventario), device binding backend (sesión única + re-vinculación con aprobación).',  'in_progress', '2026-06-03'],
        [2, 'Motor de compensación',    'Tablas ledger, reglas de liquidación, cálculo de nómina/comisiones, historial de pagos.',                                                           'backlog',     null],
        [3, 'Órdenes de trabajo',       'Asignación de tareas técnicas a colaboradores, seguimiento de estado, integración con tickets.',                                                    'backlog',     null],
        [4, 'Evaluación de desempeño',  'Indicadores KPI por colaborador, ciclos de evaluación, metas.',                                                                                     'backlog',     null],
        [5, 'Capacitación',             'Cursos internos, registro de certificaciones, vencimientos.',                                                                                       'backlog',     null],
        [6, 'App móvil MegaFamilia',    'Integración device binding con MegaFamilia, push de notificaciones laborales.',                                                                     'backlog',     null],
        [7, 'Reportes y analítica',     'Dashboard de headcount, rotación, antigüedad, distribución por departamento.',                                                                      'backlog',     null],
        [8, 'Multi-empresa',            'Soporte para múltiples razones sociales / sucursales dentro del mismo sistema.',                                                                    'backlog',     null],
        [9, 'Integración contable',     'Exportación de nómina a sistemas contables, comprobantes fiscales.',                                                                                'backlog',     null],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->phases as [$phase, $title, $scope, $status, $targetDate]) {
            DB::table('talento_roadmap_items')->updateOrInsert(
                ['phase' => $phase],
                [
                    'title'       => $title,
                    'scope'       => $scope,
                    'status'      => $status,
                    'target_date' => $targetDate,
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('talento_roadmap_items')->truncate();
    }
};
