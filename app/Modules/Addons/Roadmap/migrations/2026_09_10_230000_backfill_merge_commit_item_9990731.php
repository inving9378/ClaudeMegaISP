<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #9990731 (respuesta de #9990719): de los 45 items `completado` con `branch` sin
 * `merge_commit`, 21 ya tienen el 100% de su código integrado a main (confirmado con
 * `git rev-list --count main..<branch>` = 0 en #9990719) — solo falta el registro.
 *
 * Opción elegida (recomendada por el propio item, riesgo bajo): usar el tip de cada rama
 * (`git rev-parse <branch>`) como `merge_commit`. Ya es un ancestro confirmado de main, así que
 * no hace falta adivinar cuál commit "Integra circuito #N..." corresponde — evita el problema de
 * IDs de item reutilizados entre rondas de trabajo distintas (documentado en #9990719 sección 5,
 * caso #279).
 *
 * Guard triple por fila (id + branch + merge_commit IS NULL): si alguna de las 21 cambió desde la
 * verificación de #9990719 (reabierta, editada, o ya rellenada por otra vía), esa fila se salta en
 * vez de sobrescribirse a ciegas. Solo metadata de auditoría del propio roadmap — no toca código
 * de producto ni datos de negocio.
 */
return new class extends Migration
{
    private const BACKFILL = [
        279     => ['circuito/item-279-auto-merge-de-thomas-puede-cerrar-un-ite', 'e4743f1a14c7334b2f719063d6b99cbbec7b1358'],
        646     => ['circuito/item-646-valvula-de-contexto-instrumentarla-y-q', '6d1ede9472903e033706e3f1bb0c96f823b2f677'],
        663     => ['circuito/item-663-documentacioncorporativa-fase-1-apart', '313903d51395a82bbc571b3fd273a6a1a0d1b0ec'],
        664     => ['circuito/item-664-documentacioncorporativa-fase-2-repos', '3e68c6e421a2cf463605b634b3627ba445fd9d26'],
        739     => ['circuito/item-739-deriva-de-esquema-216-fase-2-diff-es', 'a94bf70b4cd2c8409e436466609f2f7284e9cfeb'],
        797     => ['circuito/item-797-deriva-216-fase-1a-comando-que-vacia-m', '5faa8fae969977314c8364f2d6b855e7d56e3632'],
        900     => ['circuito/item-900-auditoria-875-detector-b-null-safety', 'a4e23199384aea130a2593f2cf10d514607492c6'],
        933     => ['circuito/item-933-versiones-a-la-carta-elegir-que-cambios', 'c033d8523133c3203bec267bd8f6c14d93c61e25'],
        952     => ['circuito/item-952-mr-16-grafo-de-red-y-trazo-extremo-a-e', '666e10c6e7a7806d150faba5fc4cfdae23239619'],
        953     => ['circuito/item-953-mr-17-trazo-inverso-de-impacto-client', '5bf50f53b48d28f8bc38c4976f70ab1f59aa0684'],
        9990008 => ['circuito/item-9990008-torre-247-pieza-4-fase-34-retomar', 'c60093b2e2a3e3ce7368252adc25612e6dbc0595'],
        9990254 => ['circuito/item-9990254-wrappers-de-circuitoscheduler-que-no-mu', '6a6c12a6af2e0497733c92f4be3d697208d1f276'],
        9990331 => ['circuito/item-9990331-torre-relojtecho-por-terminal-debe-ref', '0e45e2459a85879a0527078111ba1af42cfab025'],
        9990401 => ['circuito/item-9990401-fase-2a-backend-bloque-por-persona-3', '553690cafae7c4b2744a6e69d78a2afa2981edac'],
        9990423 => ['circuito/item-9990423-mr-21-frontend-colorear-nap-por-salud', 'c419d161dce4ab675c41c5c5d95b5d807256eb95'],
        9990469 => ['circuito/item-9990469-mr-16-fase-2-dibujar-el-trazo-resaltad', 'ce7272ba089f0db58c71b9dc3fe8db846f5faad7'],
        9990510 => ['circuito/item-9990510-mr-22-fase-1b-componente-frontend-del', 'ed1ea7b3dd8fdc21d646d2b1f4139b87053afd50'],
        9990547 => ['circuito/item-9990547-mr-24e-fase-1-modo-dibujo-alta-de-nap', '65cf4e2f556b1ebfb8cc64565aa629906753bc59'],
        9990640 => ['circuito/item-9990640-merge-runner-corre-en-varwwwmegaisp-y', '6d41a7e225d180411b3d7f8c236ad2d3e5ee9c5c'],
        9990644 => ['circuito/item-9990644-fase-a-mergerunner-opera-en-worktree-d', '5952ddcbdfaf85bf002ce4c8e3f706fe52f2c107'],
        9990676 => ['circuito/item-9990676-f5-eje-del-auditor-versiones-sin-publ', 'a067baa8334dd9b90154d5f0dd97886bf877bba3'],
    ];

    public function up(): void
    {
        foreach (self::BACKFILL as $id => [$branch, $mergeCommit]) {
            DB::table('roadmap_items')
                ->where('id', $id)
                ->where('branch', $branch)
                ->whereNull('merge_commit')
                ->update(['merge_commit' => $mergeCommit]);
        }
    }

    public function down(): void
    {
        // No-op a propósito: revertir dejaría otra vez sin registro 21 items cuyo código ya está
        // confirmado en main — perdería la corrección de datos sin ganar nada.
    }
};
