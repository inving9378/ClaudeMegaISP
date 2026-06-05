<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentoEvidenceModelSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Tipos de evidencia ─────────────────────────────────────────────
        $evidenceTypes = [
            ['id'=>1,  'name'=>'Fachada (número visible)',       'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>1],
            ['id'=>2,  'name'=>'NAP + puerto',                   'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>2],
            ['id'=>3,  'name'=>'Roseta / conector óptico',       'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>3],
            ['id'=>4,  'name'=>'Equipo instalado (ONT/router)',  'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>4],
            ['id'=>5,  'name'=>'Serial (etiqueta)',              'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>5],
            ['id'=>6,  'name'=>'Lectura dBm',                   'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>true, 'es_firma'=>false,'sort_order'=>6],
            ['id'=>7,  'name'=>'Tendido / cableado',            'permite_varias'=>true, 'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>7],
            ['id'=>8,  'name'=>'Speedtest',                     'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>8],
            ['id'=>9,  'name'=>'Firma cliente',                 'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>true, 'sort_order'=>9],
            ['id'=>10, 'name'=>'Empalme / fusión',              'permite_varias'=>true, 'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>10],
            ['id'=>11, 'name'=>'Foto antes',                    'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>11],
            ['id'=>12, 'name'=>'Foto después',                  'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>12],
            ['id'=>13, 'name'=>'Equipo retirado + serial',      'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>13],
            ['id'=>14, 'name'=>'Punto desconectado / sellado',  'permite_varias'=>false,'requiere_justificacion'=>false,'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>14],
            ['id'=>15, 'name'=>'Otro',                          'permite_varias'=>true, 'requiere_justificacion'=>true, 'es_lectura_dbm'=>false,'es_firma'=>false,'sort_order'=>15],
        ];

        $now = now();
        foreach ($evidenceTypes as &$r) {
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
        }

        DB::table('talento_evidence_types')->insertOrIgnore($evidenceTypes);

        // ── 2. Matriz de requeridos ────────────────────────────────────────────
        // Formato: [ot_type_id, evidence_type_id, condition]
        // condition null = siempre obligatoria; 'cambio_equipo' = solo si hubo cambio
        $requirements = [
            // Instalación nueva (1): 1,2,3,4,5,6,8,9
            [1, 1, null], [1, 2, null], [1, 3, null], [1, 4, null],
            [1, 5, null], [1, 6, null], [1, 8, null], [1, 9, null],

            // Soporte/reparación (2): 11,12,6 + 5 si cambio (condicional)
            [2, 11, null], [2, 12, null], [2, 6, null],
            [2, 5, 'cambio_equipo'],

            // Cambio de equipo (3): 13,4,5,6
            [3, 13, null], [3, 4, null], [3, 5, null], [3, 6, null],

            // Reubicación (4): 1,2,6,9
            [4, 1, null], [4, 2, null], [4, 6, null], [4, 9, null],

            // Baja/retiro (5): 13,14,4
            [5, 13, null], [5, 14, null], [5, 4, null],
        ];

        $rows = [];
        foreach ($requirements as [$otTypeId, $evTypeId, $cond]) {
            $rows[] = [
                'ot_type_id'       => $otTypeId,
                'evidence_type_id' => $evTypeId,
                'condition'        => $cond,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        DB::table('talento_ot_type_evidence_requirements')->insertOrIgnore($rows);

        // ── 3. Umbrales dBm ───────────────────────────────────────────────────
        // Rango: dbm_min <= valor < dbm_max  (NULL = sin límite)
        // Los rangos son INCLUSIVOS en min, EXCLUSIVOS en max salvo que se indique.
        // Se evalúan en orden sort_order ASC; se usa el primer match.
        $thresholds = [
            [
                'dbm_min'      => -8.0,     // >= -8 (sin límite superior)
                'dbm_max'      => null,
                'categoria'    => 'sobrepotencia',
                'accion'       => 'bloquea',
                'mensaje'      => 'Señal demasiado fuerte (sobrepotencia). Revisa el atenuador.',
                'aplica_bono'  => false,
                'sort_order'   => 1,
            ],
            [
                'dbm_min'      => -18.0,
                'dbm_max'      => -8.01,    // -18 a -9 (exclusive -8)
                'categoria'    => 'aviso',
                'accion'       => 'alerta',
                'mensaje'      => 'Señal en zona de aviso (-9 a -18 dBm). Aceptada con marca.',
                'aplica_bono'  => false,
                'sort_order'   => 2,
            ],
            [
                'dbm_min'      => -23.0,
                'dbm_max'      => -18.01,   // -19 a -23
                'categoria'    => 'excelente',
                'accion'       => 'ok',
                'mensaje'      => 'Señal excelente.',
                'aplica_bono'  => true,
                'sort_order'   => 3,
            ],
            [
                'dbm_min'      => -26.99,
                'dbm_max'      => -23.01,   // -24 a -26.99
                'categoria'    => 'cumplio',
                'accion'       => 'ok',
                'mensaje'      => 'Señal dentro de parámetros.',
                'aplica_bono'  => true,
                'sort_order'   => 4,
            ],
            [
                'dbm_min'      => null,     // <= -27 (sin límite inferior)
                'dbm_max'      => -27.0,
                'categoria'    => 'baja_senal',
                'accion'       => 'bloquea',
                'mensaje'      => 'Señal demasiado baja (<= -27 dBm). Revisa el tendido.',
                'aplica_bono'  => false,
                'sort_order'   => 5,
            ],
        ];

        foreach ($thresholds as &$t) {
            $t['created_at'] = $now;
            $t['updated_at'] = $now;
        }

        DB::table('talento_dbm_thresholds')->insertOrIgnore($thresholds);
    }
}
