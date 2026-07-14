<?php

namespace App\Console\Commands\Scripts;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Item Roadmap #439 — Clasificar TODOS los items (nivel + prioridad +
 * módulo/footprint), prerequisito de "despachar todo".
 *
 * Barrido de datos, conservador por diseño: solo RELLENA huecos (nunca
 * pisa un valor ya puesto por un humano o por una ejecución previa) en
 * `roadmap_items.modulo` y `roadmap_items.priority`, usando la taxonomía
 * de módulo/footprint que ya usan los ~184 items clasificados a mano
 * (grep de valores distintos existentes). `nivel_riesgo` ya estaba en
 * 0 nulos al momento de este barrido — no se toca esa columna.
 *
 * Idempotente y re-ejecutable: si un id ya tiene modulo/priority no-nulo
 * y distinto de "Sin clasificar", se deja intacto.
 *
 * Uso:
 *   php artisan roadmap:clasificar-items --dry-run
 *   php artisan roadmap:clasificar-items
 */
class ClasificarRoadmapItemsCommand extends Command
{
    protected $signature = 'roadmap:clasificar-items {--dry-run}';

    protected $description = 'Item #439: rellena modulo/footprint y priority faltantes en roadmap_items (conservador, no pisa valores existentes)';

    /**
     * id => módulo/footprint (taxonomía existente; ver body del método
     * handle() para el conteo total). Incluye los 9 "Sin clasificar"
     * literales y los 228 NULL detectados en el barrido del 2026-07-14.
     */
    private const MODULO = [
        1 => 'Core / Layout',
        2 => 'ModuleManager',
        3 => 'Core / arquitectura',
        4 => 'Mapas',
        5 => 'Core / Clientes',
        6 => 'Core / arquitectura',
        7 => 'Core / arquitectura',
        8 => 'Manual',
        9 => 'IA',
        10 => 'ModuleManager',
        11 => 'ModuleManager',
        12 => 'Configuracion',
        13 => 'Vendedores',
        14 => 'Core / Layout',
        15 => 'ModuleManager',
        16 => 'Core / arquitectura',
        17 => 'Configuracion',
        18 => 'SmartImportExport',
        19 => 'MegaFamilia',
        20 => 'Embajadores',
        21 => 'MegaFamilia',
        22 => 'Core / arquitectura',
        23 => 'MegaFamilia',
        24 => 'MegaFamilia',
        25 => 'MegaFamilia',
        26 => 'MegaFamilia',
        27 => 'MegaFamilia',
        28 => 'MegaFamilia',
        29 => 'MegaFamilia',
        30 => 'MegaFamilia',
        31 => 'MegaFamilia',
        32 => 'MegaFamilia',
        33 => 'Talento',
        34 => 'Talento',
        35 => 'Finanzas',
        36 => 'GestionRed',
        37 => 'VoIP',
        38 => 'Security',
        39 => 'Security',
        40 => 'Security',
        41 => 'Security',
        42 => 'Core / Layout',
        43 => 'ModuleManager',
        44 => 'ModuleManager',
        45 => 'Inventario',
        46 => 'Marketing',
        47 => 'Marketing',
        48 => 'CobranzaBlaster',
        49 => 'MegaFamilia',
        50 => 'Marketing',
        51 => 'WhatsAppAgent',
        52 => 'Voice',
        53 => 'Core / Layout',
        54 => 'Manual',
        55 => 'Payments',
        57 => 'GestionRed',
        58 => 'Flotas',
        59 => 'Flotas',
        60 => 'Flotas',
        61 => 'Flotas',
        62 => 'Flotas',
        63 => 'Flotas',
        64 => 'Flotas',
        65 => 'Flotas',
        66 => 'Flotas',
        67 => 'CobranzaBlaster',
        68 => 'ModuleManager',
        69 => 'ModuleManager',
        70 => 'Configuracion',
        71 => 'Usuarios / Permisos',
        72 => 'MegaFamilia',
        73 => 'MegaFamilia',
        74 => 'MegaFamilia',
        75 => 'MegaFamilia',
        76 => 'Configuracion',
        77 => 'WhatsAppAgent',
        78 => 'Configuracion',
        79 => 'CRM',
        80 => 'CRM',
        81 => 'CRM',
        82 => 'Flotas',
        83 => 'Security',
        84 => 'Core / arquitectura',
        85 => 'Clientes',
        86 => 'GestionRed',
        87 => 'Core / arquitectura',
        88 => 'MegaFamilia',
        89 => 'Security',
        90 => 'Flotas',
        91 => 'Flotas',
        92 => 'Flotas',
        93 => 'Flotas',
        94 => 'Core / arquitectura',
        95 => 'CobranzaBlaster',
        96 => 'Flotas',
        97 => 'MegaFamilia',
        98 => 'Auth',
        99 => 'Flotas',
        100 => 'Flotas',
        101 => 'Flotas',
        102 => 'Flotas',
        103 => 'Flotas',
        104 => 'MegaFamilia',
        105 => 'Auth',
        106 => 'Core / arquitectura',
        107 => 'Manual',
        108 => 'Flotas',
        109 => 'Core / arquitectura',
        110 => 'Core / Layout',
        111 => 'Core / Layout',
        112 => 'Flotas',
        113 => 'Usuarios / Permisos',
        114 => 'Usuarios / Permisos',
        115 => 'Usuarios / Permisos',
        116 => 'Core / Layout',
        117 => 'Finanzas',
        118 => 'Release',
        119 => 'Core / Layout',
        120 => 'Core / Layout',
        121 => 'Talento',
        122 => 'Talento',
        123 => 'Talento',
        124 => 'Talento',
        125 => 'Talento',
        126 => 'Usuarios / Permisos',
        127 => 'ModuleManager',
        128 => 'ModuleManager',
        129 => 'ModuleManager',
        130 => 'ModuleManager',
        131 => 'Core / Layout',
        132 => 'ModuleManager',
        133 => 'WarRoom',
        134 => 'WarRoom',
        135 => 'WarRoom',
        136 => 'Roadmap / Circuito CC',
        137 => 'Core / Layout',
        138 => 'Core / Layout',
        139 => 'Security',
        140 => 'Release',
        141 => 'Usuarios / Permisos',
        142 => 'PortalPago',
        143 => 'PortalPago',
        144 => 'PortalCliente',
        145 => 'Security',
        146 => 'Release',
        147 => 'Planes',
        148 => 'PortalCliente',
        149 => 'PortalCliente',
        150 => 'PortalCliente',
        151 => 'PortalCliente',
        152 => 'PortalCliente',
        153 => 'Auth',
        154 => 'PortalCliente',
        155 => 'Clientes',
        156 => 'Security',
        157 => 'Security',
        158 => 'Configuracion',
        159 => 'Finanzas',
        160 => 'Payments',
        161 => 'PortalPago',
        162 => 'PortalPago',
        163 => 'PortalPago',
        164 => 'PortalPago',
        165 => 'Manual',
        166 => 'Manual',
        167 => 'Manual',
        168 => 'Core / Layout',
        169 => 'CRM',
        170 => 'Vendedores',
        171 => 'IA',
        172 => 'IA',
        173 => 'Finanzas',
        174 => 'ModuleManager',
        175 => 'Finanzas',
        176 => 'Security',
        177 => 'Flotas',
        178 => 'Core / arquitectura',
        179 => 'PortalPago',
        180 => 'PortalPago',
        181 => 'Core / Layout',
        182 => 'PortalPago',
        183 => 'PortalPago',
        184 => 'PortalPago',
        185 => 'VoIP',
        186 => 'VoIP',
        187 => 'CobranzaBlaster',
        188 => 'VoIP',
        189 => 'VoIP',
        190 => 'VoIP',
        191 => 'PortalPago',
        192 => 'PortalPago',
        193 => 'PortalPago',
        194 => 'PortalPago',
        195 => 'PortalPago',
        196 => 'WhatsAppAgent',
        197 => 'WhatsAppAgent',
        198 => 'WhatsAppAgent',
        199 => 'WhatsAppAgent',
        200 => 'WhatsAppAgent',
        201 => 'WhatsAppAgent',
        327 => 'Roadmap / Circuito CC',
        328 => 'Roadmap / Circuito CC',
        329 => 'Roadmap / Circuito CC',
        330 => 'Roadmap / Circuito CC',
        331 => 'Roadmap / Circuito CC',
        332 => 'Roadmap / Circuito CC',
        333 => 'Roadmap / Circuito CC',
        334 => 'Roadmap / Circuito CC',
        336 => 'Roadmap / Circuito CC',
        338 => 'Circuito / Revisor',
        339 => 'Circuito / Revisor',
        343 => 'Roadmap / Circuito CC',
        344 => 'Roadmap / Circuito CC',
        345 => 'Roadmap / Torre de control',
        346 => 'Roadmap / Circuito CC',
        347 => 'Roadmap / Torre de control',
        348 => 'Roadmap / Circuito CC',
        349 => 'Roadmap / Torre de control',
        350 => 'Circuito / Revisor',
        352 => 'Roadmap / Circuito CC',
        356 => 'Roadmap / Circuito CC',
        357 => 'Roadmap / Circuito CC',
        358 => 'Manual',
        411 => 'Roadmap / Torre de control',
        412 => 'Roadmap / Torre de control',
        417 => 'Roadmap / Circuito CC',
        418 => 'Roadmap / Circuito CC',
        425 => 'Roadmap / Circuito CC',
        434 => 'Roadmap / Circuito CC',
        435 => 'Roadmap / Torre de control',
        436 => 'Roadmap / Torre de control',
        437 => 'Roadmap / Circuito CC',
        438 => 'Roadmap / Circuito CC',
        439 => 'Roadmap / Circuito CC',
        440 => 'Roadmap / Circuito CC',
        455 => 'Roadmap / Torre de control',
        464 => 'Roadmap / Circuito CC',
    ];

    /** id => prioridad (solo los que llegaron con priority NULL). */
    private const PRIORITY = [
        11 => 'alta',
        12 => 'alta',
        13 => 'media',
        14 => 'baja',
        84 => 'alta',
        165 => 'media',
        209 => 'media',
        328 => 'alta',
        330 => 'baja',
        334 => 'alta',
        338 => 'alta',
        349 => 'media',
        419 => 'alta',
        420 => 'media',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $moduloUpdated = 0;
        $moduloSkipped = 0;
        foreach (self::MODULO as $id => $modulo) {
            $item = RoadmapItem::find($id);
            if (! $item) {
                $this->warn("  #{$id}: no existe, saltado.");
                continue;
            }
            if ($item->modulo !== null && $item->modulo !== 'Sin clasificar') {
                $moduloSkipped++;
                continue;
            }
            $moduloUpdated++;
            if (! $dryRun) {
                $item->modulo = $modulo;
                $item->save();
            }
        }

        $priorityUpdated = 0;
        $prioritySkipped = 0;
        foreach (self::PRIORITY as $id => $priority) {
            $item = RoadmapItem::find($id);
            if (! $item) {
                $this->warn("  #{$id}: no existe, saltado.");
                continue;
            }
            if ($item->priority !== null) {
                $prioritySkipped++;
                continue;
            }
            $priorityUpdated++;
            if (! $dryRun) {
                $item->priority = $priority;
                $item->save();
            }
        }

        $this->table(
            ['Columna', 'Actualizados', 'Ya tenían valor (saltados)'],
            [
                ['modulo', $moduloUpdated, $moduloSkipped],
                ['priority', $priorityUpdated, $prioritySkipped],
            ]
        );

        $restantesModulo = RoadmapItem::where(function ($q) {
            $q->whereNull('modulo')->orWhere('modulo', 'Sin clasificar');
        })->count();
        $restantesPriority = RoadmapItem::whereNull('priority')->count();
        $this->line("Restantes sin modulo: {$restantesModulo} | Restantes sin priority: {$restantesPriority}");

        if ($dryRun) {
            $this->warn('DRY-RUN: no se escribió nada. Quita --dry-run para aplicar.');
        } else {
            $this->info('Clasificación aplicada.');
        }

        return self::SUCCESS;
    }
}
