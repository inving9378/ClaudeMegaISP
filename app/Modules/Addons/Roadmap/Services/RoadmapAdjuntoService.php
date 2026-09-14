<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapAdjunto;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * #9991163 — Subida, amarre y borrado de adjuntos del roadmap.
 *
 * Orden INMUTABLE de `subir()`: validar extensión (D3) → validar tamaño (D4) → validar MIME REAL
 * con finfo sobre el archivo temporal (D4) → sha256 → dedupe (D6) → recién entonces escribir en
 * disco con nombre generado (D7) → registrar. Si algo falla ANTES de escribir, no se toca el
 * disco; si falla el registro DESPUÉS de escribir, el archivo recién escrito se borra en el
 * mismo acto. Así nunca quedan archivos huérfanos (punto 2).
 */
class RoadmapAdjuntoService
{
    /**
     * @param  int[]  $itemIds  items a los que amarrar en el mismo acto (opcional, D2)
     * @return array{adjunto: RoadmapAdjunto, duplicado: bool}
     * @throws ValidationException  con el motivo EXACTO del rechazo (extensión, tamaño o tipo real)
     */
    public function subir(UploadedFile $archivo, ?string $descripcion, ?int $userId, array $itemIds = []): array
    {
        if (! $archivo->isValid()) {
            throw ValidationException::withMessages(['archivo' => 'La subida llegó incompleta o corrupta (' . $archivo->getErrorMessage() . ').']);
        }

        $nombreOriginal = $this->nombreLimpio($archivo->getClientOriginalName());
        $ext = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        // D3 — extensión: allowlist cerrada.
        if ($ext === '' || ! array_key_exists($ext, RoadmapAdjunto::EXTENSIONES)) {
            throw ValidationException::withMessages(['archivo' => sprintf(
                'Extensión no permitida (.%s). Permitidas: %s.',
                $ext !== '' ? $ext : '—', implode(', ', array_keys(RoadmapAdjunto::EXTENSIONES))
            )]);
        }

        // D4 — tamaño.
        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0) {
            throw ValidationException::withMessages(['archivo' => 'El archivo está vacío.']);
        }
        if ($tamano > RoadmapAdjunto::MAX_BYTES) {
            throw ValidationException::withMessages(['archivo' => sprintf(
                'Demasiado grande: %s (límite %d MB).', $this->humano($tamano), RoadmapAdjunto::MAX_BYTES / 1048576
            )]);
        }

        // D4 — MIME REAL leído del contenido (finfo), no del nombre ni del navegador.
        $mimeReal = $this->mimeReal($archivo->getRealPath());
        if (! in_array($mimeReal, RoadmapAdjunto::EXTENSIONES[$ext], true)) {
            throw ValidationException::withMessages(['archivo' => sprintf(
                'El contenido no corresponde a la extensión: dice .%s pero el tipo real es %s.', $ext, $mimeReal
            )]);
        }

        // D6 — dedupe por hash del contenido.
        $hash = hash_file('sha256', $archivo->getRealPath());
        if ($hash === false) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo para calcular su hash.']);
        }

        $existente = RoadmapAdjunto::withTrashed()->where('hash_sha256', $hash)->first();
        if ($existente) {
            return DB::transaction(function () use ($existente, $descripcion, $userId, $itemIds, $archivo) {
                if ($existente->trashed() || $existente->disco_purgado_at !== null || ! $existente->existeEnDisco()) {
                    // Borrado lógico (o purgado ya de disco): re-subirlo lo revive con el mismo id.
                    $this->escribirEnDisco($archivo, $existente->ruta);
                    $existente->deleted_at = null;
                    $existente->purgar_despues_de = null;
                    $existente->disco_purgado_at = null;
                }
                $existente->veces_subido = $existente->veces_subido + 1;
                if ($descripcion !== null && $descripcion !== '' && ($existente->descripcion === null || $existente->descripcion === '')) {
                    $existente->descripcion = $descripcion;
                }
                $existente->save();
                $this->amarrarVarios($existente, $itemIds, $userId);

                return ['adjunto' => $existente->fresh(['items', 'subidoPor']), 'duplicado' => true];
            });
        }

        // D7 — nombre en disco generado por el sistema; el original queda como metadato.
        $ruta = (string) Str::uuid() . '.' . $ext;
        $this->escribirEnDisco($archivo, $ruta);

        try {
            return DB::transaction(function () use ($nombreOriginal, $ruta, $ext, $hash, $mimeReal, $tamano, $descripcion, $userId, $itemIds) {
                $adjunto = RoadmapAdjunto::create([
                    'nombre_original' => $nombreOriginal,
                    'ruta'            => $ruta,
                    'extension'       => $ext,
                    'hash_sha256'     => $hash,
                    'mime'            => $mimeReal,
                    'tamano'          => $tamano,
                    'descripcion'     => $descripcion !== '' ? $descripcion : null,
                    'veces_subido'    => 1,
                    'subido_por'      => $userId,
                ]);
                $this->amarrarVarios($adjunto, $itemIds, $userId);

                return ['adjunto' => $adjunto->fresh(['items', 'subidoPor']), 'duplicado' => false];
            });
        } catch (\Throwable $e) {
            // El registro falló DESPUÉS de escribir: se retira el archivo para no dejar huérfanos.
            Storage::disk(RoadmapAdjunto::DISCO)->delete($ruta);
            throw $e;
        }
    }

    public function amarrar(RoadmapAdjunto $adjunto, int $itemId, ?int $userId): void
    {
        RoadmapItem::query()->findOrFail($itemId, ['id']);
        $adjunto->items()->syncWithoutDetaching([
            $itemId => ['amarrado_por' => $userId, 'created_at' => now()],
        ]);
    }

    public function desamarrar(RoadmapAdjunto $adjunto, int $itemId): void
    {
        $adjunto->items()->detach($itemId);
    }

    /**
     * Punto 5 — borrado LÓGICO; el archivo sigue en disco DIAS_RETENCION días (purgarVencidos()).
     * Si está amarrado a un item abierto, NO se borra: primero se desamarra.
     *
     * @throws ValidationException
     */
    public function borrar(RoadmapAdjunto $adjunto): void
    {
        $abiertos = $adjunto->itemsAbiertos()->pluck('roadmap_items.id')->all();
        if ($abiertos) {
            throw ValidationException::withMessages(['adjunto' => sprintf(
                'Está amarrado a %d item(s) abierto(s) (#%s): desamárralo primero.', count($abiertos), implode(', #', $abiertos)
            )]);
        }
        $adjunto->purgar_despues_de = now()->addDays(RoadmapAdjunto::DIAS_RETENCION);
        $adjunto->save();
        $adjunto->delete();
    }

    /** Quita de disco los archivos cuyo plazo de retención ya venció. Devuelve cuántos. */
    public function purgarVencidos(): int
    {
        $n = 0;
        RoadmapAdjunto::onlyTrashed()
            ->whereNull('disco_purgado_at')
            ->where('purgar_despues_de', '<=', now())
            ->each(function (RoadmapAdjunto $a) use (&$n) {
                Storage::disk(RoadmapAdjunto::DISCO)->delete($a->ruta);
                $a->disco_purgado_at = now();
                $a->save();
                $n++;
            });

        return $n;
    }

    /** Fase 3 — adjuntos registrados de un item que NO están en disco (guard fail-closed). */
    public function faltantesEnDisco(RoadmapItem $item): array
    {
        return $item->adjuntos()->get()
            ->reject(fn (RoadmapAdjunto $a) => $a->existeEnDisco())
            ->map(fn (RoadmapAdjunto $a) => ['id' => $a->id, 'nombre' => $a->nombre_original, 'ruta' => $a->rutaAbsoluta()])
            ->values()->all();
    }

    /**
     * Fase 3 (punto 12) — bloque que se antepone al prompt de la terminal al DESPACHAR, con la ruta
     * ABSOLUTA en disco de cada adjunto y su descripción. El texto del item en BD no se toca.
     * Devuelve null si el item no tiene adjuntos.
     */
    public function bloquePrompt(RoadmapItem $item): ?string
    {
        $adjuntos = $item->adjuntos()->orderBy('roadmap_adjunto_item.created_at')->get();
        if ($adjuntos->isEmpty()) {
            return null;
        }
        $lineas = $adjuntos->map(fn (RoadmapAdjunto $a) => sprintf(
            '- %s — %s%s',
            $a->rutaAbsoluta(),
            $a->descripcion ?: $a->nombre_original,
            $a->descripcion ? " (archivo: {$a->nombre_original})" : ''
        ))->implode("\n");

        return "## ADJUNTOS DE ESTE ITEM (léelos antes de empezar)\n"
            . $lineas . "\n"
            . "Estos archivos son parte del contrato del item (maquetas, capturas, evidencia). Léelos con "
            . "Read/cat ANTES de decidir nada; si uno no abre, detente y repórtalo — no construyas a ciegas.";
    }

    /**
     * Fase 3 (punto 13) — GUARD FAIL-CLOSED. Si algún adjunto registrado NO existe en disco, el
     * item se marca (requiere_irving + motivo_espera=adjunto_faltante + motivo con las rutas) y se
     * avisa en el log del item y en el canal de despacho. Devuelve los faltantes (vacío = puede
     * arrancar). Trabajar a ciegas sobre un contrato faltante fue lo que produjo #9990934.
     */
    public function guardAdjuntosEnDisco(RoadmapItem $item, string $por = 'guard-adjuntos'): array
    {
        $faltantes = $this->faltantesEnDisco($item);
        if (! $faltantes) {
            return [];
        }
        $rutas = implode(', ', array_map(fn ($f) => "#{$f['id']} {$f['nombre']} → {$f['ruta']}", $faltantes));
        $motivo = 'Adjunto(s) registrado(s) que NO están en disco: ' . $rutas
            . '. La vuelta no arranca hasta re-subirlos desde Panorama → Adjuntos o desamarrarlos del item.';

        $item->estado_aprobacion = 'requiere_irving';
        $item->motivo_espera     = 'adjunto_faltante';
        $item->motivo_bloqueo    = Str::limit($motivo, 2000, '…');
        $item->worker_sid        = null;
        $item->claimed_at        = null;
        $log   = is_array($item->log) ? $item->log : [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => 'adjunto_faltante_no_arranca', 'motivo' => $motivo, 'faltantes' => $faltantes];
        $item->log = $log;
        $item->save();

        Log::channel('circuito_despacho')->warning("#{$item->id} NO ARRANCA — adjunto faltante en disco (fail-closed): {$rutas}");

        return $faltantes;
    }

    // ── internos ─────────────────────────────────────────────────────────────────────────────

    private function amarrarVarios(RoadmapAdjunto $adjunto, array $itemIds, ?int $userId): void
    {
        foreach (array_unique(array_map('intval', $itemIds)) as $id) {
            if ($id > 0) {
                $this->amarrar($adjunto, $id, $userId);
            }
        }
    }

    private function escribirEnDisco(UploadedFile $archivo, string $ruta): void
    {
        $disco = Storage::disk(RoadmapAdjunto::DISCO);
        $stream = fopen($archivo->getRealPath(), 'rb');
        if ($stream === false || ! $disco->put($ruta, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            Log::channel('roadmap_externo')->error('adjunto-escritura-fallo', ['ruta' => $ruta, 'disco' => $disco->path('')]);
            throw ValidationException::withMessages(['archivo' => 'No se pudo escribir el archivo en el almacenamiento de adjuntos.']);
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function mimeReal(string $rutaTemporal): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $rutaTemporal) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        return is_string($mime) && $mime !== '' ? strtolower($mime) : 'application/octet-stream';
    }

    /** D7 — el nombre del navegador es solo metadato: se recorta, sin rutas ni caracteres de control. */
    private function nombreLimpio(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre) ?? $nombre;

        return Str::limit(trim($nombre) !== '' ? trim($nombre) : 'archivo', 250, '');
    }

    private function humano(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }
}
