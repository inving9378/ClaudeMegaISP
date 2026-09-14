<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapAdjunto;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * #9991165 (Fase 3, punto 15) — Promueve un adjunto al repositorio cuando es un contrato duradero
 * (una maqueta, p. ej.): lo COPIA a docs/<destino>/ con su nombre original saneado y entrega el
 * comando de commit para que Irving decida si se versiona. NUNCA hace `git add`/`git commit` solo:
 * los archivos subidos por UI no entran al historial sin una decisión humana.
 */
class AdjuntoPromoverCommand extends Command
{
    protected $signature = 'roadmap:adjunto-promover {id : ID del adjunto}
        {--destino=docs/maquetas : carpeta dentro del repo (relativa a la raíz)}
        {--nombre= : nombre de archivo destino (default: el original saneado)}
        {--force : sobreescribir si ya existe}';

    protected $description = '#9991165 — copia un adjunto a docs/ y entrega el comando de commit (sin commitear).';

    public function handle(): int
    {
        $a = RoadmapAdjunto::find($this->argument('id'));
        if (! $a) {
            $this->error('Adjunto no encontrado.');

            return self::FAILURE;
        }
        if (! $a->existeEnDisco()) {
            $this->error("El adjunto #{$a->id} no está en disco ({$a->rutaAbsoluta()}).");

            return self::FAILURE;
        }

        $destino = trim((string) $this->option('destino'), '/');
        if ($destino === '' || str_contains($destino, '..') || ! str_starts_with($destino, 'docs')) {
            $this->error('El destino debe estar dentro de docs/ (p. ej. docs/maquetas).');

            return self::FAILURE;
        }
        $nombre = (string) ($this->option('nombre') ?: $a->nombre_original);
        $nombre = basename(str_replace('\\', '/', $nombre));
        $ext    = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));
        $base   = Str::slug((string) pathinfo($nombre, PATHINFO_FILENAME)) ?: 'adjunto-' . $a->id;
        if ($ext !== '' && $ext !== strtolower($a->extension)) {
            $this->error("La extensión del nombre destino (.{$ext}) no coincide con la del adjunto (.{$a->extension}).");

            return self::FAILURE;
        }
        $archivo  = $base . '.' . $a->extension;
        $dirAbs   = base_path($destino);
        $rutaRel  = $destino . '/' . $archivo;
        $rutaAbs  = $dirAbs . '/' . $archivo;

        if (is_file($rutaAbs) && ! $this->option('force')) {
            $this->error("Ya existe {$rutaRel}. Usa --force para sobreescribir o --nombre para otro nombre.");

            return self::FAILURE;
        }
        if (! is_dir($dirAbs) && ! mkdir($dirAbs, 0775, true) && ! is_dir($dirAbs)) {
            $this->error("No se pudo crear {$destino}/.");

            return self::FAILURE;
        }
        if (! copy($a->rutaAbsoluta(), $rutaAbs)) {
            $this->error('No se pudo copiar el archivo.');

            return self::FAILURE;
        }

        $items = $a->items()->pluck('roadmap_items.id')->map(fn ($i) => "#{$i}")->implode(', ');
        $this->info("Copiado: {$rutaRel} ({$this->humano($a->tamano)}, sha256 {$a->hash_sha256})");
        $this->line('');
        $this->line('Para versionarlo (lo decides tú — este comando NO commitea):');
        $this->line("  git add {$rutaRel}");
        $this->line(sprintf(
            "  git commit -m %s",
            escapeshellarg(sprintf('docs: %s — adjunto #%d del roadmap%s%s', $archivo, $a->id, $items ? " (items {$items})" : '', $a->descripcion ? ' — ' . $a->descripcion : ''))
        ));

        return self::SUCCESS;
    }

    private function humano(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }
}
