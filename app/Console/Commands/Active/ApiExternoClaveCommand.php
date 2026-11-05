<?php

namespace App\Console\Commands\Active;

use App\Models\ApiExternoClave;
use Illuminate\Console\Command;

/**
 * Item #1159 — claves de las consultas públicas (`/api/externo/*`).
 *
 *   php artisan api-externo:clave crear "Sofía (Vercel)"      → muestra la clave UNA sola vez
 *   php artisan api-externo:clave listar
 *   php artisan api-externo:clave revocar 3
 */
class ApiExternoClaveCommand extends Command
{
    protected $signature = 'api-externo:clave
        {accion : crear | listar | revocar}
        {valor? : el nombre (crear) o el número de la clave (revocar)}
        {--alcance=cobertura : qué consulta abre la clave}';

    protected $description = 'Crea, lista o revoca las claves de las consultas públicas (/api/externo/*)';

    public function handle(): int
    {
        return match ($this->argument('accion')) {
            'crear'   => $this->crear(),
            'listar'  => $this->listar(),
            'revocar' => $this->revocar(),
            default   => $this->fallo('Acción desconocida. Usa: crear, listar o revocar.'),
        };
    }

    private function crear(): int
    {
        $nombre = trim((string) $this->argument('valor'));
        if ($nombre === '') {
            return $this->fallo('Falta el nombre: php artisan api-externo:clave crear "Sofía (Vercel)"');
        }

        try {
            [$registro, $clave] = ApiExternoClave::generar($nombre, (string) $this->option('alcance'));
        } catch (\InvalidArgumentException $e) {
            return $this->fallo($e->getMessage());
        }

        $this->info("Clave #{$registro->id} «{$registro->nombre}» para la consulta de {$registro->alcance}:");
        $this->newLine();
        $this->line($clave);
        $this->newLine();
        $this->warn('Cópiala ahora: no se guarda y no se puede volver a ver. Si se pierde, revócala y crea otra.');
        $this->line('Se manda en el encabezado:  Authorization: Bearer <la clave>');

        return self::SUCCESS;
    }

    private function listar(): int
    {
        $filas = ApiExternoClave::orderBy('id')->get()->map(fn (ApiExternoClave $c) => [
            $c->id,
            $c->nombre,
            $c->alcance,
            $c->prefijo . '…',
            $c->created_at?->format('Y-m-d H:i'),
            $c->ultimo_uso_at?->format('Y-m-d H:i') ?? '—',
            $c->usos,
            $c->revocada_at ? 'revocada ' . $c->revocada_at->format('Y-m-d') : 'vigente',
        ]);

        if ($filas->isEmpty()) {
            $this->line('No hay claves.');

            return self::SUCCESS;
        }

        $this->table(['#', 'Nombre', 'Consulta', 'Empieza con', 'Creada', 'Último uso', 'Usos', 'Estado'], $filas);

        return self::SUCCESS;
    }

    private function revocar(): int
    {
        $clave = ApiExternoClave::find((int) $this->argument('valor'));
        if (! $clave) {
            return $this->fallo('No existe esa clave. Mira los números con: php artisan api-externo:clave listar');
        }
        if ($clave->revocada_at) {
            $this->line("La clave #{$clave->id} ya estaba revocada.");

            return self::SUCCESS;
        }

        $clave->update(['revocada_at' => now()]);
        $this->info("Clave #{$clave->id} «{$clave->nombre}» revocada: deja de funcionar desde ya.");

        return self::SUCCESS;
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
