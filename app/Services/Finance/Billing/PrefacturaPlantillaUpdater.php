<?php

namespace App\Services\Finance\Billing;

use Illuminate\Support\Facades\DB;

/**
 * Ajuste mínimo de la plantilla «Prefactura.» de document_templates para que la alimente el
 * PrefacturaDataProvider (item #480). Lo comparten la migración (automática, conservadora) y
 * `php artisan prefactura:actualizar-plantilla` (manual, con --dry-run / --force).
 *
 * Cambios, todos anclados a texto exacto (si un ancla no aparece exactamente una vez, NO se toca):
 *  1. Logo: la ruta de disco «horneada» por el editor (/var/www/ClaudeMegaISP/...) → ${data.logo_pdf}.
 *  2. Celda de IVA: el literal «16%» → ${data.iva_monto} (el monto en pesos).
 *  3-4. Las menciones de ${data.table_client_services} / ${data.conceptos_filas} DENTRO de
 *     comentarios se reescriben sin «${…}»: el validador reemplaza también dentro de comentarios
 *     y metía la tabla de servicios en un comentario CSS.
 *  5. (item #505) Debajo del título, en pequeño, ${data.documento_nota} («Sin valor fiscal»).
 *
 * Cada cambio es idempotente: si su resultado ya está en el HTML se cuenta como «ya estaba» y no
 * se vuelve a aplicar. Así la misma migración sirve desde el original (prod) o desde la versión
 * con solo los cambios 1-4 (dev tras #480).
 *
 * Se busca SIEMPRE por nombre, nunca por id (los ids de dev y prod no coinciden).
 */
class PrefacturaPlantillaUpdater
{
    public const NOMBRE = 'Prefactura.';

    /** sha1 del HTML de prod (2026-09-21, copia en dev del 2026-10-15) antes del ajuste. */
    public const HASH_ORIGINAL = '24ee6f557e0dd5c1df7425f93e93426ca89ab252';

    /** sha1 de transformar(original): la plantilla ya ajustada (cambios 1-5). */
    public const HASH_APLICADA = 'bf9c30bb28b53a2960192e61ea2d9632b1acd9e3';

    /** sha1 con solo los cambios 1-4 (lo que quedó en dev con #480, antes de #505). */
    public const HASH_APLICADA_V1 = 'c7ac7c38a4f9ca446f7490a5a490d50896f803b8';

    public const SITUACION_ORIGINAL = 'original';
    public const SITUACION_VERSION_ANTERIOR = 'version_anterior';
    public const SITUACION_APLICADA = 'ya_aplicada';
    public const SITUACION_DISTINTA = 'distinta';
    public const SITUACION_NO_EXISTE = 'no_existe';

    /** [descripción, buscar, reemplazar, esRegex] */
    private const CAMBIOS = [
        [
            'logo: ruta de disco horneada → ${data.logo_pdf}',
            '#<img class="logo" src="[^"$]*"#',
            '<img class="logo" src="${data.logo_pdf}"',
            true,
        ],
        [
            'celda de IVA: literal 16% → ${data.iva_monto}',
            '<tr><td class="k">IVA trasladado 16%</td><td class="v">16%</td></tr>',
            '<tr><td class="k">IVA trasladado 16%</td><td class="v">${data.iva_monto}</td></tr>',
            false,
        ],
        [
            'comentario CSS sin ${…}',
            '   ${data.table_client_services} mientras no exista ${data.conceptos_filas} */',
            '   data.table_client_services mientras no exista data.conceptos_filas */',
            false,
        ],
        [
            'comentario HTML sin ${…}',
            'y dejar en su lugar:   <div class="svc">${data.table_client_services}</div>',
            'y dejar en su lugar un <div class="svc"> con la variable data.table_client_services',
            false,
        ],
        [
            'nota «Sin valor fiscal» bajo el título',
            '<div class="doctype">${data.documento_titulo}</div>',
            '<div class="doctype">${data.documento_titulo}</div><div style="font-size:6.8pt;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6E7C8C;margin:1px 0 2px 0;">${data.documento_nota}</div>',
            false,
        ],
    ];

    /** Lo que deja cada cambio de CAMBIOS (para reconocer «ya estaba»), en el mismo orden. */
    private const RESULTADOS = [
        '<img class="logo" src="${data.logo_pdf}"',
        '<td class="v">${data.iva_monto}</td>',
        '   data.table_client_services mientras no exista data.conceptos_filas */',
        'y dejar en su lugar un <div class="svc"> con la variable data.table_client_services',
        '<div style="font-size:6.8pt;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6E7C8C;margin:1px 0 2px 0;">${data.documento_nota}</div>',
    ];

    /** @return object{id:int,html:string}|null */
    public function plantilla(): ?object
    {
        return DB::table('document_templates')
            ->where('name', self::NOMBRE)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first(['id', 'html']);
    }

    public function situacion(?object $plantilla = null): string
    {
        $plantilla ??= $this->plantilla();
        if (! $plantilla) {
            return self::SITUACION_NO_EXISTE;
        }

        return match (sha1((string) $plantilla->html)) {
            self::HASH_ORIGINAL    => self::SITUACION_ORIGINAL,
            self::HASH_APLICADA_V1 => self::SITUACION_VERSION_ANTERIOR,
            self::HASH_APLICADA    => self::SITUACION_APLICADA,
            default                => self::SITUACION_DISTINTA,
        };
    }

    /**
     * Aplica los cambios anclados. No escribe nada.
     *
     * @return array{html: string, aplicados: string[], ya_estaban: string[], faltan: string[]}
     */
    public function transformar(string $html): array
    {
        $aplicados = [];
        $yaEstaban = [];
        $faltan = [];

        foreach (self::CAMBIOS as $i => [$descripcion, $buscar, $reemplazar, $esRegex]) {
            // Primero «ya estaba»: el cambio 5 contiene su propia ancla y se duplicaría.
            if (substr_count($html, self::RESULTADOS[$i]) === 1) {
                $yaEstaban[] = $descripcion;
                continue;
            }
            $veces = $esRegex ? preg_match_all($buscar, $html) : substr_count($html, $buscar);
            if ($veces !== 1) {
                $faltan[] = "{$descripcion} (ancla encontrada {$veces} veces, se esperaba 1)";
                continue;
            }
            $html = $esRegex
                ? preg_replace_callback($buscar, fn () => $reemplazar, $html, 1)
                : str_replace($buscar, $reemplazar, $html);
            $aplicados[] = $descripcion;
        }

        return ['html' => $html, 'aplicados' => $aplicados, 'ya_estaban' => $yaEstaban, 'faltan' => $faltan];
    }

    /**
     * @param bool $force aplicar aunque el HTML no sea uno conocido (solo si TODAS las anclas
     *                    aparecen); sin $force, solo se toca el original o la versión 1-4 exactos.
     * @return array{situacion: string, escrito: bool, motivo: string, antes: ?string,
     *               despues: ?string, aplicados: string[], ya_estaban: string[], faltan: string[], id: ?int}
     */
    public function aplicar(bool $force = false, bool $dryRun = false): array
    {
        $plantilla = $this->plantilla();
        $situacion = $this->situacion($plantilla);
        $base = ['situacion' => $situacion, 'escrito' => false, 'antes' => $plantilla?->html,
                 'despues' => null, 'aplicados' => [], 'ya_estaban' => [], 'faltan' => [], 'id' => $plantilla?->id];

        if ($situacion === self::SITUACION_NO_EXISTE) {
            return $base + ['motivo' => 'No existe la plantilla «' . self::NOMBRE . '».'];
        }
        if ($situacion === self::SITUACION_APLICADA) {
            return $base + ['motivo' => 'La plantilla ya tiene el ajuste; no hay nada que hacer.'];
        }

        $t = $this->transformar((string) $plantilla->html);
        $base = array_merge($base, ['despues' => $t['html'], 'aplicados' => $t['aplicados'],
            'ya_estaban' => $t['ya_estaban'], 'faltan' => $t['faltan']]);

        if (! $t['aplicados'] && ! $t['faltan']) {
            return $base + ['motivo' => 'Todos los cambios ya estaban; no hay nada que hacer.'];
        }
        if ($situacion === self::SITUACION_DISTINTA && ! $force) {
            return $base + ['motivo' => 'El HTML no es el original esperado (alguien la editó); no se toca. '
                . 'Revisa con --dry-run y aplica con --force si el diff es correcto.'];
        }
        if ($t['faltan']) {
            return $base + ['motivo' => 'Faltan anclas; no se toca: ' . implode('; ', $t['faltan'])];
        }
        if ($dryRun) {
            return $base + ['motivo' => 'Dry-run: no se escribió nada.'];
        }

        DB::table('document_templates')->where('id', $plantilla->id)->update([
            'html'       => $t['html'],
            'updated_at' => now(),
        ]);

        return array_merge($base, ['escrito' => true]) + ['motivo' => 'Plantilla actualizada.'];
    }

    /** Diff línea a línea (los cambios no agregan ni quitan líneas). */
    public static function diff(?string $antes, ?string $despues): array
    {
        $a = preg_split("/\r\n|\n/", (string) $antes);
        $b = preg_split("/\r\n|\n/", (string) $despues);
        $lineas = [];
        for ($i = 0, $n = max(count($a), count($b)); $i < $n; $i++) {
            if (($a[$i] ?? null) !== ($b[$i] ?? null)) {
                $lineas[] = sprintf('@@ línea %d', $i + 1);
                if (isset($a[$i])) {
                    $lineas[] = '- ' . $a[$i];
                }
                if (isset($b[$i])) {
                    $lineas[] = '+ ' . $b[$i];
                }
            }
        }

        return $lineas;
    }
}
