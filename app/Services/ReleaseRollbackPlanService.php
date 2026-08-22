<?php

namespace App\Services;

use App\Models\Release;
use App\Services\Deploy\ReleaseTechnicalLinkService;

/**
 * Item roadmap #1021 (sub-item 5/5 de #1012) — mitad "prod" de la épica: en producción NO hay
 * botón que ejecute nada, solo este generador de un documento revisable que Irving lee y ejecuta
 * A MANO fuera del circuito (el circuito nunca toca prod — ver CLAUDE.md items #156/#157).
 *
 * Reusa en solo-lectura los servicios de los sub-items 1 (ReleaseTechnicalLinkService, vínculo
 * técnico) y 4 (ReleaseReversibilityService, ventana de filas nuevas) — no duplica su cálculo.
 *
 * La mitad "dev" (botón que SÍ ejecuta checkout + migrate:rollback) queda como sub-item aparte:
 * depende de que el sub-item 3 (#1019, down() probado up→down→up) exista para poder afirmar con
 * datos que cada down() es seguro, en vez de asumirlo.
 */
class ReleaseRollbackPlanService
{
    public function __construct(
        private ReleaseTechnicalLinkService $vinculo,
        private ReleaseReversibilityService $reversibilidad
    ) {
    }

    /**
     * @return array{
     *   version:string, commit_sha:?string, migraciones:array<int,string>,
     *   migraciones_reversas:array<int,string>, reversible:?bool, reversible_motivo:?string,
     *   snapshot_bd:?string, estado:array, down_verificado:bool
     * }
     */
    public function datosPara(Release $release): array
    {
        $migraciones = $this->vinculo->migracionesEnRango($release->migracion_desde, $release->migracion_hasta)->all();

        return [
            'version'              => $release->version,
            'commit_sha'           => $release->commit_sha,
            'migraciones'          => $migraciones,
            'migraciones_reversas' => array_reverse($migraciones),
            'reversible'           => $release->reversible,
            'reversible_motivo'    => $release->reversible_motivo,
            'snapshot_bd'          => $release->snapshot_bd,
            'estado'               => $this->reversibilidad->estadoPara($release),
            // Sub-item #1019 (down() probado up→down→up) todavía no existe en el sistema:
            // no se puede afirmar con datos que el down() de cada migración es seguro.
            'down_verificado'      => false,
        ];
    }

    public function generarMarkdown(Release $release): string
    {
        $d = $this->datosPara($release);
        $l = [];

        $l[] = "# Plan de regreso — {$d['version']}";
        $l[] = '';
        $l[] = '> Generado automáticamente por la Torre de control. Documento de SOLO LECTURA: revísalo';
        $l[] = '> y ejecútalo a mano en el servidor de producción. El circuito nunca toca producción.';
        $l[] = '';

        // --- Reversibilidad / advertencias ---
        $l[] = '## 0. Antes de nada — estado de reversibilidad';
        $l[] = '';
        if ($d['reversible'] === false) {
            $l[] = '**🔴 NO REVERSIBLE.** ' . ($d['reversible_motivo'] ?: 'Sin motivo registrado.');
            $l[] = '';
            $l[] = 'Esta versión no tiene un camino de regreso conocido. Continuar de todos modos es';
            $l[] = 'decisión tuya, con los ojos abiertos — este documento describe el intento, no una garantía.';
        } else {
            $estado = $d['estado']['estado'] ?? 'sin_datos';
            $etiqueta = [
                'limpio'       => '🟢 REGRESO LIMPIO — sin filas nuevas por encima del umbral.',
                'con_perdida'  => '🟡 REGRESO CON PÉRDIDA — algunas tablas superaron su umbral de filas nuevas.',
                'no_reversible' => '🔴 NO REVERSIBLE.',
                'sin_datos'    => '⚪ SIN DATOS — no hay snapshot para medir esta ventana (versión previa al mecanismo, o recién desplegada sin snapshot).',
            ][$estado] ?? '⚪ SIN DATOS.';
            $l[] = $etiqueta;
            if (!empty($d['estado']['detalle'])) {
                $l[] = '';
                $l[] = '| Tabla | Criticidad | Filas nuevas | Umbral |';
                $l[] = '|---|---|---|---|';
                foreach ($d['estado']['detalle'] as $fila) {
                    $marca = $fila['filas_nuevas'] > $fila['umbral'] ? ' ⚠' : '';
                    $l[] = "| {$fila['tabla']} | {$fila['criticidad']} | {$fila['filas_nuevas']}{$marca} | {$fila['umbral']} |";
                }
            }
        }
        $l[] = '';
        $l[] = '**⚠ down() no verificado automáticamente.** El sub-item que prueba up→down→up de cada';
        $l[] = 'migración (roadmap #1019) todavía no existe en el sistema — no se puede afirmar con datos';
        $l[] = 'que el down() de las migraciones listadas abajo revierte limpio. Revísalas a mano antes de';
        $l[] = 'correr `migrate:rollback` en producción.';
        $l[] = '';

        // --- Precondiciones ---
        $l[] = '## 1. Precondiciones';
        $l[] = '';
        $l[] = '- [ ] Ventana de mantenimiento acordada (el sitio puede quedar inestable durante el regreso).';
        $l[] = '- [ ] Nadie más desplegando en paralelo (candado del pipeline de deploy).';
        $l[] = '- [ ] Acceso SSH al servidor de producción confirmado.';
        if ($d['snapshot_bd']) {
            $l[] = "- [ ] Respaldo de BD previo a esta versión disponible en: `{$d['snapshot_bd']}`.";
        } else {
            $l[] = '- [ ] ⚠ No hay `snapshot_bd` registrado para esta versión — toma un respaldo manual antes de continuar (`backup_db:process` o `mysqldump` directo).';
        }
        $l[] = '- [ ] Respaldo NUEVO (de la BD actual, antes de retroceder) — por si el regreso mismo falla a medias.';
        $l[] = '';

        // --- Checkout ---
        $l[] = '## 2. Checkout del código';
        $l[] = '';
        if ($d['commit_sha']) {
            $l[] = '```bash';
            $l[] = 'cd /var/www/megaisp';
            $l[] = "git fetch origin";
            $l[] = "git checkout {$d['commit_sha']}";
            $l[] = '```';
        } else {
            $l[] = '⚠ Esta versión no tiene `commit_sha` registrado (anterior al vínculo técnico del sub-item 1,';
            $l[] = 'roadmap #1017). No hay comando de checkout confiable — el commit exacto se perdió.';
        }
        $l[] = '';

        // --- Migraciones a revertir ---
        $l[] = '## 3. Migraciones a revertir (en orden, la más nueva primero)';
        $l[] = '';
        if ($d['migraciones_reversas']) {
            foreach ($d['migraciones_reversas'] as $m) {
                $l[] = "- `{$m}` — down() **no verificado** (ver advertencia arriba)";
            }
            $l[] = '';
            $l[] = '```bash';
            $l[] = 'php artisan migrate:rollback --step=' . count($d['migraciones_reversas']);
            $l[] = '```';
            $l[] = '';
            $l[] = 'Verifica el resultado de cada `down()` contra este documento antes de continuar — si alguna';
            $l[] = 'falla o dropea algo inesperado, DETENTE y restaura el respaldo del paso 1 en vez de forzar.';
        } else {
            $l[] = 'No se registró rango de migraciones para esta versión (`migracion_desde`/`migracion_hasta`';
            $l[] = 'vacíos) — probablemente una versión que no trajo migraciones, o anterior al vínculo técnico.';
        }
        $l[] = '';

        // --- Warm-up ---
        $l[] = '## 4. Warm-up (después del checkout y las migraciones)';
        $l[] = '';
        $l[] = '```bash';
        $l[] = 'php artisan view:clear && php artisan config:clear && php artisan route:clear';
        $l[] = 'php artisan config:auditar-env && php artisan config:cache';
        $l[] = 'php artisan queue:restart';
        $l[] = '```';
        $l[] = '';
        $l[] = '`config:cache` SOLO detrás de `config:auditar-env` (ver CLAUDE.md item #790) — nunca suelto.';
        $l[] = '';

        // --- Verificación posterior ---
        $l[] = '## 5. Verificación posterior';
        $l[] = '';
        $l[] = '- [ ] El sitio carga y el login funciona.';
        $l[] = '- [ ] `php artisan migrate:status` refleja el rango esperado.';
        $l[] = '- [ ] Revisar `storage/logs/laravel.log` en busca de errores nuevos.';
        $l[] = '- [ ] Probar el flujo crítico que motivó el regreso.';
        $l[] = '';

        $l[] = '---';
        $l[] = '*Este plan es un documento, no una ejecución. Irving lo revisa y lo corre a mano en producción.*';

        return implode("\n", $l);
    }
}
