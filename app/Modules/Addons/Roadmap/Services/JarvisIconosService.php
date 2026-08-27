<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Facades\Log;

/**
 * LA IDENTIDAD VISUAL DE JARVIS — una sola fuente para todos los lugares donde aparece.
 *
 * ── POR QUÉ UN SERVICIO Y NO UNA RUTA EN CADA VISTA ─────────────────────────────────────────────
 *
 * El icono sale en la burbuja cerrada, en la cabecera del chat abierto, en los avisos y en su
 * pestaña. Con la ruta escrita a mano en cada sitio, cambiar el icono es cambiar cuatro archivos y
 * olvidarse de uno — y el que se olvida no rompe nada, sólo deja una cara distinta en una esquina.
 * Ese es justo el tipo de incoherencia que hace que un sistema se vea descuidado sin que nadie
 * sepa señalar dónde. Aquí hay UNA respuesta y todos preguntan lo mismo.
 *
 * ── EL SET ES FIJO Y VERSIONADO ─────────────────────────────────────────────────────────────────
 *
 * No hay subida de archivos desde el panel (decisión de Irving): se elige entre un set que vive en
 * `public/assets/jarvis/` y viaja en git. Menos superficie que asegurar —nada de validar binarios
 * subidos por HTTP— y el historial de la identidad queda en el repositorio como cualquier otro
 * cambio. Los PNG originales se dejan en una carpeta fuera del repo y entran con
 * `php artisan jarvis:iconos-importar`.
 *
 * ── LOS DOS AVISOS DEL CATÁLOGO ─────────────────────────────────────────────────────────────────
 *
 * `tiene_texto` — el diseño lleva la palabra JARVIS escrita. A 48 px esa palabra es una mancha, así
 * que esos NO se ofrecen para la burbuja: quedan en su propio grupo (pantalla de inicio,
 * documentación).
 * `legible_48`  — a 48 px la figura se sigue leyendo. Varios diseños con trazos de circuito se
 * convierten en ruido a ese tamaño. Se marca en el selector para que la elección sea informada, en
 * vez de descubrirlo después.
 */
class JarvisIconosService
{
    /** Tamaños que se generan de cada icono. 48 es la burbuja; 512 es para pantalla de inicio. */
    public const TAMANOS = [48, 96, 192, 512];

    /** Carpeta pública (versionada) donde viven los generados. */
    public const DIR_PUBLICO = 'assets/jarvis';

    /** Carpeta de ORIGEN, fuera del repo: ahí deja Irving los PNG. */
    public const DIR_ORIGEN = '/home/meganet/jarvis-iconos';

    /** @var array<string,mixed>|null caché en memoria del manifiesto por request */
    private ?array $manifiesto = null;

    public function rutaManifiesto(): string
    {
        return public_path(self::DIR_PUBLICO . '/manifest.json');
    }

    /** @return array<string,mixed> */
    public function manifiesto(): array
    {
        if ($this->manifiesto !== null) {
            return $this->manifiesto;
        }

        $vacio = ['generado_en' => null, 'iconos' => []];

        try {
            $raw = @file_get_contents($this->rutaManifiesto());
            $j   = $raw === false ? null : json_decode($raw, true);
            $this->manifiesto = is_array($j) ? ($j + $vacio) : $vacio;
        } catch (\Throwable $e) {
            Log::warning('jarvis-iconos: no se pudo leer el manifiesto', ['error' => $e->getMessage()]);
            $this->manifiesto = $vacio;
        }

        return $this->manifiesto;
    }

    /**
     * Todos los iconos del catálogo, cada uno con sus URLs por tamaño.
     *
     * @return array<int,array<string,mixed>>
     */
    public function disponibles(): array
    {
        $out = [];
        foreach ((array) ($this->manifiesto()['iconos'] ?? []) as $ic) {
            if (! is_array($ic) || empty($ic['slug'])) {
                continue;
            }
            $out[] = $ic + [
                'nombre'      => $ic['slug'],
                'tiene_texto' => false,
                'legible_48'  => true,
                'nota'        => null,
                'urls'        => $this->urls((string) $ic['slug']),
            ];
        }

        return $out;
    }

    /** Los que SÍ se pueden usar en la burbuja: sin la palabra JARVIS escrita. */
    public function paraBurbuja(): array
    {
        return array_values(array_filter($this->disponibles(), fn ($i) => empty($i['tiene_texto'])));
    }

    /** Los que llevan la palabra escrita: pantalla de inicio y documentación, no burbuja. */
    public function soloPantalla(): array
    {
        return array_values(array_filter($this->disponibles(), fn ($i) => ! empty($i['tiene_texto'])));
    }

    /** @return array<int,string> URLs por tamaño, con cache-busting por mtime. */
    public function urls(string $slug): array
    {
        $urls = [];
        foreach (self::TAMANOS as $px) {
            $rel  = self::DIR_PUBLICO . "/{$slug}/{$px}.png";
            $abs  = public_path($rel);
            $urls[$px] = is_file($abs) ? asset($rel) . '?v=' . filemtime($abs) : null;
        }

        return $urls;
    }

    /**
     * El slug ELEGIDO, ya validado contra el catálogo.
     *
     * Si lo guardado ya no existe (se quitó del set), devuelve `null` en vez de una ruta rota: la
     * burbuja cae a su icono de fábrica y la pantalla lo dice. Un icono que apunta a un 404 se ve
     * como un sistema descuidado, y aquí el archivo puede desaparecer por un simple `git pull`.
     */
    public function elegido(): ?string
    {
        try {
            $slug = app(TorreConfigService::class)->get()->jarvis_icono;
        } catch (\Throwable) {
            return null;   // sin base no hay elección que leer; la burbuja usa el de fábrica
        }

        if (! $slug) {
            return null;
        }

        foreach ($this->disponibles() as $ic) {
            if ($ic['slug'] === $slug && ! empty($ic['urls'][48])) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * LO QUE CONSUMEN TODAS LAS VISTAS. Un solo método: si mañana el icono se sirve distinto, se
     * cambia aquí y cambia en los cuatro lugares a la vez.
     *
     * @return array{slug:?string, urls:array<int,?string>, hay_catalogo:bool, motivo:?string}
     */
    public function identidad(): array
    {
        $slug  = $this->elegido();
        $todos = $this->disponibles();

        return [
            'slug'         => $slug,
            'urls'         => $slug ? $this->urls($slug) : [],
            'hay_catalogo' => $todos !== [],
            'motivo'       => $slug !== null ? null : ($todos === []
                ? 'No hay iconos importados todavía: deja los PNG en ' . self::DIR_ORIGEN
                  . ' y corre `php artisan jarvis:iconos-importar`.'
                : 'No has elegido icono: JARVIS usa el de fábrica.'),
        ];
    }

    /** ¿Este slug se puede elegir para la burbuja? Valida contra el catálogo, no contra el disco. */
    public function esElegible(string $slug): bool
    {
        foreach ($this->paraBurbuja() as $ic) {
            if ($ic['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }
}
