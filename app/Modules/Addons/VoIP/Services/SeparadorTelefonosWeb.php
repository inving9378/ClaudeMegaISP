<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Seeders\PlanNumeracionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pasa las gemelas viejas (`web1011`, ligadas a una extensión física) a teléfonos web con
 * número propio en 2000–2999 (SegmentosTelefono):
 *
 *   - con dueño        → mismo teléfono, con el primer número disponible de su área que no
 *                        se parezca a un aparato (web1011 → 2005, nunca 2011 si existe la 1011);
 *                        se guarda el viejo en `numero_anterior` para el historial.
 *   - sin dueño        → se quita: ningún navegador la puede usar (el teléfono web sale de la
 *                        cuenta de la persona) y solo dejaba al aparato «ligado».
 *   - dos o más de la misma persona → se queda una (la que hoy usa su navegador: activa y la
 *                        más antigua; si ya tiene una de 2000–2999, esa) y las otras se quitan,
 *                        pasándole antes sus grupos de timbrado.
 *   - teléfono web que se parece a un aparato (2011 y existe la 1011, como los dejó la V1.96)
 *                      → pasa a un número disponible: el aparato y el navegador son dos lugares
 *                        distintos y no deben confundirse (Irving, 20-nov-2026).
 *
 * Lo corre SincronizadorAsterisk antes que todo lo demás (pantalla «Sincronizar con
 * Asterisk» y paso «Sincronizar» de «Instalar dependencias»), así que llega a producción
 * con el instalador. revisar(false) solo lista; revisar(true) lo hace. Idempotente: sin
 * gemelas ni parecidos no hace nada.
 */
class SeparadorTelefonosWeb
{
    private array $tareas = [];
    private array $fallas = [];

    public function __construct(private AsteriskProvisioningService $prov)
    {
    }

    /** @return array{tareas: list<string>, fallas: list<string>} */
    public function revisar(bool $aplicar): array
    {
        $this->tareas = $this->fallas = [];

        $gemelas = Extension::with('agente')->where('es_webrtc', true)->orderBy('id')->get()
            ->filter(fn (Extension $e) => SegmentosTelefono::baseDeGemela($e->numero) !== null)
            ->values();
        $parecidos = $this->parecidosAUnAparato();
        if ($gemelas->isEmpty() && $parecidos->isEmpty()) {
            return ['tareas' => [], 'fallas' => []];
        }

        if ($aplicar) {
            // Los rangos 2000–2999 los siembra el instalador; por si se sincroniza antes.
            (new PlanNumeracionSeeder())->run();
        }

        $quedan    = $this->lineaPorPersona();
        $apartados = [];

        foreach ($gemelas as $g) {
            $persona = $this->persona($g);

            if (! $g->user_id) {
                $this->hacer($aplicar, "Quitar {$g->numero}: teléfono web sin dueño (los teléfonos físicos ya no llevan línea web ligada)",
                    fn () => $this->quitar($g, null));
                continue;
            }

            $queda = $quedan[$g->user_id];
            if ($queda->id !== $g->id) {
                $this->hacer($aplicar, "Quitar {$g->numero}: {$persona} ya tiene otro teléfono web (uno por persona); sus grupos pasan al que se queda",
                    fn () => $this->quitar($g, $queda));
                continue;
            }

            $base  = SegmentosTelefono::baseDeGemela($g->numero);
            $nuevo = SegmentosTelefono::disponible($g->rango ?? SegmentosTelefono::rangoDe($base), $apartados);
            if ($nuevo === null) {
                $this->fallas[] = "{$g->numero} ({$persona}): no hay números libres en 2000–2999.";
                continue;
            }
            $apartados[] = $nuevo;

            $this->hacer($aplicar, "{$g->numero} → {$nuevo}: teléfono web de {$persona} con número propio (recarga la página para tomarlo)",
                fn () => $this->renumerar($g, $nuevo));
        }

        foreach ($parecidos as $w) {
            $persona = $this->persona($w);
            $nuevo   = SegmentosTelefono::disponible($w->rango ?? SegmentosTelefono::rangoDe($w->numero), $apartados);
            if ($nuevo === null) {
                $this->fallas[] = "{$w->numero} ({$persona}): no hay números libres en 2000–2999.";
                continue;
            }
            $apartados[] = $nuevo;

            $aparato = SegmentosTelefono::parecido($w->numero);
            $this->hacer($aplicar, "{$w->numero} → {$nuevo}: teléfono web de {$persona}; ya no se parece al aparato {$aparato} (recarga la página para tomarlo)",
                fn () => $this->renumerar($w, $nuevo));
        }

        return ['tareas' => $this->tareas, 'fallas' => $this->fallas];
    }

    /**
     * La línea web que se queda de cada persona: la que ya está en 2000–2999 si hay; si
     * no, la activa más antigua (la misma que hoy le da MiTelefonoController).
     *
     * @return array<int, Extension> user_id => extensión
     */
    private function lineaPorPersona(): array
    {
        return Extension::where('es_webrtc', true)->whereNotNull('user_id')->orderBy('id')->get()
            ->groupBy('user_id')
            ->map(fn (Collection $lineas) => $lineas->sortBy(fn (Extension $e) => [
                SegmentosTelefono::esNumeroWeb($e->numero) ? 0 : 1,
                $e->activo ? 0 : 1,
                $e->id,
            ])->first())
            ->all();
    }

    /**
     * Teléfonos web de 2000–2999 que se parecen a un aparato que existe (2011 con la 1011).
     *
     * @return Collection<int, Extension>
     */
    private function parecidosAUnAparato(): Collection
    {
        $web = Extension::with('agente')->where('es_webrtc', true)
            ->whereBetween('numero', [SegmentosTelefono::WEB_DESDE, SegmentosTelefono::WEB_HASTA])
            ->orderBy('id')->get()
            ->filter(fn (Extension $e) => SegmentosTelefono::esNumeroWeb($e->numero));
        $aparatos = Extension::whereIn('numero', $web->map(fn (Extension $e) => SegmentosTelefono::parecido($e->numero)))
            ->pluck('numero')->flip();

        return $web->filter(fn (Extension $e) => isset($aparatos[SegmentosTelefono::parecido($e->numero)]))->values();
    }

    private function renumerar(Extension $g, string $nuevo): void
    {
        $viejo = $g->numero;
        // El número que se veía: el del aparato en una gemela (web1011 → 1011), si no el propio.
        $visible = SegmentosTelefono::baseDeGemela($viejo) ?? $viejo;

        // El endpoint de Asterisk se llama como el número: el viejo se quita y se publica el nuevo.
        $this->prov->desprovisionarExtension($g);

        // El identificador que ve quien recibe la llamada: con el viejo, devolver la llamada
        // timbraba al aparato y no al navegador.
        $callerid = (string) $g->callerid;
        if ($callerid === '' || $callerid === $visible || $callerid === $viejo) {
            $callerid = $nuevo;
        } elseif (str_contains($callerid, "<{$visible}>")) {
            $callerid = str_replace("<{$visible}>", "<{$nuevo}>", $callerid);
        }

        $g->update([
            'numero'                   => $nuevo,
            // Se conserva la gemela vieja (web1003) si la había: es la de casi todo el historial.
            'numero_anterior'          => SegmentosTelefono::baseDeGemela((string) $g->numero_anterior) !== null
                ? $g->numero_anterior : $viejo,
            'callerid'                 => $callerid,
            'voip_rango_numeracion_id' => SegmentosTelefono::rangoDe($nuevo)?->id ?? $g->voip_rango_numeracion_id,
        ]);

        if ($g->activo) {
            $this->prov->provisionarExtension($g->fresh());
        }
    }

    /** Quita una línea web; si `$queda` viene, antes le pasa sus grupos de timbrado. */
    private function quitar(Extension $g, ?Extension $queda): void
    {
        if ($queda) {
            foreach (DB::table('voip_grupo_extension')->where('extension_id', $g->id)->get() as $m) {
                DB::table('voip_grupo_extension')->insertOrIgnore([
                    'grupo_id' => $m->grupo_id, 'extension_id' => $queda->id, 'orden' => $m->orden,
                ]);
            }
        }
        $this->prov->desprovisionarExtension($g);
        $g->delete();   // la liga con grupos se borra en cascada
    }

    private function persona(Extension $g): string
    {
        return $g->agente ? trim("{$g->agente->name} {$g->agente->father_last_name}") : $g->nombre;
    }

    private function hacer(bool $aplicar, string $texto, callable $accion): void
    {
        if (! $aplicar) {
            $this->tareas[] = $texto;

            return;
        }
        try {
            $accion();
            $this->tareas[] = $texto;
        } catch (\Throwable $e) {
            $this->fallas[] = "{$texto} — falló: {$e->getMessage()}";
        }
    }
}
