<?php

namespace App\Console\Commands\Active;

use App\Support\ArchivoObligatorio;
use App\Support\ArchivoObligatorioException;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * FAC-07 (item #514, padre #482): cancelar (NUNCA borrar) colas viejas de notificaciones de cobro.
 * Lo ejecuta Irving en producción; aquí solo se prepara y se prueba.
 *
 * Tres pasos, ninguno amplía lo aprobado:
 *  1. --preparar: selección EXPLÍCITA (cola, tipos, estados, fecha límite) → manifiesto JSON con los
 *     ids y la foto de cada fila (estado y updated_at), en storage/app/billing/manifiestos (fuera de
 *     Git). Imprime su sha256: eso es lo que Irving aprueba.
 *  2. --manifiesto=… (dry-run por defecto): revalida cada fila contra la foto e informa cuántas se
 *     cancelarían y cuántas cambiaron. Con --ejecutar --sha256=<aprobado>: por lotes, dentro de una
 *     transacción con bloqueo, cancela SOLO las filas del manifiesto que siguen idénticas a la foto
 *     y deja un archivo de resultado.
 *  3. --revertir=<resultado> (dry-run por defecto; --ejecutar para aplicar): devuelve su estado solo a
 *     las filas que siguen exactamente como las dejó esta cancelación (no restaura en masa lo que
 *     alguien modificó después).
 *
 * No toca billing_config.envio_pausado, ni workers, ni el candado de 7 días de
 * app:send-all-emails-command. Un manifiesto de otra base de datos (p. ej. dev) se rechaza.
 */
class CancelarColasCobroCommand extends Command
{
    protected $signature = 'billing:cancelar-colas
                            {--preparar : Generar el manifiesto con la selección}
                            {--cola= : billing_notifications | invoice_emails | payment_emails | proforma_invoice_emails}
                            {--tipos= : Valores explícitos (document_type o via), separados por coma}
                            {--estados= : Estados explícitos a cancelar, separados por coma}
                            {--hasta= : Solo filas creadas ANTES de esta fecha (AAAA-MM-DD)}
                            {--desde= : Opcional: solo filas creadas desde esta fecha}
                            {--manifiesto= : Ruta del manifiesto a revalidar / ejecutar}
                            {--sha256= : sha256 aprobado del manifiesto (obligatorio con --ejecutar)}
                            {--revertir= : Ruta del archivo de resultado a revertir}
                            {--ejecutar : Aplicar de verdad (sin esto, solo informa)}
                            {--lote=500 : Filas por lote}';

    protected $description = 'Cancela (no borra) colas viejas de notificaciones de cobro con manifiesto aprobado (FAC-07)';

    /** cola => [tabla, columna de tipo, estados permitidos, valor de cancelado, columna de nota] */
    public const COLAS = [
        'billing_notifications'   => ['billing_notifications', 'document_type', ['en_espera', 'error'], 'cancelado', 'error_log'],
        'invoice_emails'          => ['invoice_emails', 'via', ['pending', 'failed'], 'cancelled', 'error_message'],
        'payment_emails'          => ['payment_emails', 'via', ['pending', 'failed'], 'cancelled', 'error_message'],
        'proforma_invoice_emails' => ['proforma_invoice_emails', 'via', ['pending', 'failed'], 'cancelled', 'error_message'],
    ];

    public const DIRECTORIO = 'billing/manifiestos';

    public function handle(): int
    {
        return match (true) {
            (bool) $this->option('preparar')    => $this->preparar(),
            (bool) $this->option('revertir')    => $this->revertir(),
            (bool) $this->option('manifiesto')  => $this->aplicar(),
            default => $this->fallar('Indica --preparar, --manifiesto=… o --revertir=… (ver --help).'),
        };
    }

    // ─── 1. Preparar ──────────────────────────────────────────────────────────────

    private function preparar(): int
    {
        $cola = (string) $this->option('cola');
        if (! isset(self::COLAS[$cola])) {
            return $this->fallar('--cola debe ser una de: ' . implode(', ', array_keys(self::COLAS)) . '.');
        }
        [$tabla, $colTipo, $permitidos] = self::COLAS[$cola];

        $tipos = self::lista($this->option('tipos'));
        $estados = self::lista($this->option('estados'));
        if (! $tipos || ! $estados) {
            return $this->fallar('--tipos y --estados son obligatorios y explícitos (sin comodines).');
        }
        if ($fuera = array_diff($estados, $permitidos)) {
            return $this->fallar('Estados no cancelables en ' . $cola . ': ' . implode(', ', $fuera) . ' (permitidos: ' . implode(', ', $permitidos) . ').');
        }
        $hasta = self::fecha($this->option('hasta'));
        if (! $hasta) {
            return $this->fallar('--hasta=AAAA-MM-DD es obligatorio.');
        }
        $desde = $this->option('desde') ? self::fecha($this->option('desde')) : null;
        if ($this->option('desde') && ! $desde) {
            return $this->fallar('--desde no es una fecha AAAA-MM-DD válida.');
        }

        $filas = DB::table($tabla)->whereIn($colTipo, $tipos)->whereIn('status', $estados)
            ->where('created_at', '<', $hasta->toDateString())
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde->toDateString()))
            ->orderBy('id')->get(['id', 'status', 'updated_at']);

        $manifiesto = [
            'version'     => 1,
            'cola'        => $cola,
            'base_datos'  => DB::connection()->getDatabaseName(),
            'servidor'    => gethostname(),
            'preparado_at' => now()->toIso8601String(),
            'criterios'   => ['tipos' => $tipos, 'estados' => $estados, 'hasta' => $hasta->toDateString(), 'desde' => $desde?->toDateString()],
            'total'       => $filas->count(),
            'filas'       => $filas->map(fn ($f) => [(int) $f->id, (string) $f->status, (string) $f->updated_at])->all(),
        ];
        $json = json_encode($manifiesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $ruta = self::DIRECTORIO . '/' . $cola . '-' . now()->format('Ymd-His') . '.json';
        try {
            ArchivoObligatorio::guardar($ruta, $json);
        } catch (ArchivoObligatorioException $e) {
            return $this->fallar($e->getMessage());
        }

        $this->info("Manifiesto: storage/app/{$ruta}");
        $this->info("Cola {$cola}: {$manifiesto['total']} filas (tipos " . implode(',', $tipos) . '; estados ' . implode(',', $estados)
            . '; creadas antes de ' . $hasta->toDateString() . ($desde ? ' y desde ' . $desde->toDateString() : '') . ').');
        $this->info('sha256: ' . hash('sha256', $json));
        $this->line('Siguiente: revisar el manifiesto y, si se aprueba, correr el dry-run con --manifiesto=… (no se modificó nada).');

        return self::SUCCESS;
    }

    // ─── 2. Revalidar / ejecutar ──────────────────────────────────────────────────

    private function aplicar(): int
    {
        $ruta = (string) $this->option('manifiesto');
        [$m, $json, $error] = $this->leer($ruta);
        if ($error) {
            return $this->fallar($error);
        }
        if (($m['base_datos'] ?? null) !== DB::connection()->getDatabaseName()) {
            return $this->fallar("El manifiesto es de la base «{$m['base_datos']}» y esta es «" . DB::connection()->getDatabaseName() . '». No se aplica.');
        }
        if (! isset(self::COLAS[$m['cola'] ?? ''])) {
            return $this->fallar('Cola desconocida en el manifiesto.');
        }
        [$tabla, , , $cancelado, $colNota] = self::COLAS[$m['cola']];

        $ejecutar = (bool) $this->option('ejecutar');
        $sha = hash('sha256', $json);
        if ($ejecutar && $this->option('sha256') !== $sha) {
            return $this->fallar("--sha256 no coincide con el manifiesto ({$sha}). No se ejecuta.");
        }

        $foto = [];
        foreach ($m['filas'] as [$id, $estado, $updatedAt]) {
            $foto[(int) $id] = [$estado, $updatedAt];
        }
        $nota = 'Cancelada por FAC-07 (billing:cancelar-colas, manifiesto ' . substr($sha, 0, 12) . ') ' . now()->format('Y-m-d H:i');
        $resultado = ['manifiesto' => $ruta, 'manifiesto_sha256' => $sha, 'tabla' => $tabla, 'cancelado' => $cancelado,
            'columna_nota' => $colNota, 'ejecutado_at' => now()->toIso8601String(), 'cancelados' => [], 'omitidos' => []];

        // Un archivo por ejecución, nunca se sobrescribe (cada uno es lo único que permite revertir esa corrida).
        // Se aparta ANTES de cancelar: si no se puede escribir, no se cancela nada.
        $salida = null;
        if ($ejecutar) {
            do {
                $salida = preg_replace('/\.json$/', '', $ruta) . '-resultado-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
            } while (Storage::disk('local')->exists($salida));
            try {
                ArchivoObligatorio::guardar($salida, json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } catch (ArchivoObligatorioException $e) {
                return $this->fallar($e->getMessage());
            }
        }

        foreach (array_chunk(array_keys($foto), max(1, (int) $this->option('lote'))) as $ids) {
            DB::transaction(function () use ($tabla, $ids, $foto, $cancelado, $colNota, $nota, $ejecutar, &$resultado) {
                $actuales = DB::table($tabla)->whereIn('id', $ids)->when($ejecutar, fn ($q) => $q->lockForUpdate())
                    ->get(['id', 'status', 'updated_at', $colNota])->keyBy('id');

                $vigentes = [];
                foreach ($ids as $id) {
                    $fila = $actuales[$id] ?? null;
                    if (! $fila) {
                        $resultado['omitidos'][] = [$id, 'ya no existe'];
                    } elseif ((string) $fila->status !== $foto[$id][0] || (string) $fila->updated_at !== $foto[$id][1]) {
                        $resultado['omitidos'][] = [$id, "cambió desde el manifiesto ({$foto[$id][0]} → {$fila->status})"];
                    } else {
                        $vigentes[$id] = $fila;
                    }
                }
                if (! $ejecutar || ! $vigentes) {
                    $resultado['cancelados'] = array_merge($resultado['cancelados'], array_map(fn ($id) => [$id], array_keys($vigentes)));

                    return;
                }

                $ahora = now()->toDateTimeString();
                DB::table($tabla)->whereIn('id', array_keys($vigentes))->update(['status' => $cancelado, $colNota => $nota, 'updated_at' => $ahora]);
                foreach ($vigentes as $id => $fila) {
                    $resultado['cancelados'][] = [$id, (string) $fila->status, (string) $fila->updated_at, $ahora, $fila->{$colNota}];
                }
            });
        }

        $n = count($resultado['cancelados']);
        $o = count($resultado['omitidos']);
        if (! $ejecutar) {
            $this->info("DRY-RUN: se cancelarían {$n} filas de {$tabla}; {$o} se omitirían (cambiaron o ya no existen). Nada se modificó.");
            $this->line("Para ejecutar: --manifiesto={$ruta} --ejecutar --sha256={$sha}");

            return self::SUCCESS;
        }

        $final = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (! Storage::disk('local')->put($salida, $final)) {
            // Ya se canceló: el resultado no se pierde, queda en el log y en pantalla para revertir a mano.
            Log::critical('[billing:cancelar-colas] no se pudo guardar el resultado para revertir', ['ruta' => $salida, 'resultado' => $resultado]);
            $this->error("No se pudo guardar storage/app/{$salida}. Resultado para revertir:");
            $this->line($final);

            return self::FAILURE;
        }
        Log::info('[billing:cancelar-colas] ejecutado', ['tabla' => $tabla, 'cancelados' => $n, 'omitidos' => $o, 'manifiesto_sha256' => $sha, 'resultado' => $salida]);
        $this->info("Cancelados: {$n}. Omitidos: {$o}. Resultado (para revertir): storage/app/{$salida}");

        return self::SUCCESS;
    }

    // ─── 3. Revertir ──────────────────────────────────────────────────────────────

    private function revertir(): int
    {
        $ruta = (string) $this->option('revertir');
        [$r, , $error] = $this->leer($ruta, 'cancelados');
        if ($error) {
            return $this->fallar($error);
        }
        $tabla = $r['tabla'];
        $colNota = $r['columna_nota'];
        if (! in_array($tabla, array_column(self::COLAS, 0), true)) {
            return $this->fallar('Tabla desconocida en el resultado.');
        }
        $ejecutar = (bool) $this->option('ejecutar');
        $restaurar = $saltar = 0;

        foreach (array_chunk($r['cancelados'], max(1, (int) $this->option('lote'))) as $lote) {
            DB::transaction(function () use ($tabla, $colNota, $r, $lote, $ejecutar, &$restaurar, &$saltar) {
                $actuales = DB::table($tabla)->whereIn('id', array_column($lote, 0))->when($ejecutar, fn ($q) => $q->lockForUpdate())
                    ->get(['id', 'status', 'updated_at'])->keyBy('id');
                foreach ($lote as [$id, $estadoOriginal, , $canceladoAt, $notaOriginal]) {
                    $fila = $actuales[$id] ?? null;
                    // Solo lo que sigue EXACTAMENTE como lo dejó la cancelación.
                    if (! $fila || $fila->status !== $r['cancelado'] || (string) $fila->updated_at !== $canceladoAt) {
                        $saltar++;
                        continue;
                    }
                    $restaurar++;
                    if ($ejecutar) {
                        DB::table($tabla)->where('id', $id)->update(['status' => $estadoOriginal, $colNota => $notaOriginal, 'updated_at' => now()]);
                    }
                }
            });
        }

        $this->info(($ejecutar ? 'Restaurados' : 'DRY-RUN: se restaurarían') . ": {$restaurar}. Sin tocar (cambiaron después o no existen): {$saltar}.");
        if ($ejecutar) {
            Log::info('[billing:cancelar-colas] revertido', ['tabla' => $tabla, 'restaurados' => $restaurar, 'saltados' => $saltar, 'resultado' => $ruta]);
        }

        return self::SUCCESS;
    }

    // ─── Apoyo ────────────────────────────────────────────────────────────────────

    /** @return array{0: ?array, 1: ?string, 2: ?string} [datos, json, error] */
    private function leer(string $ruta, string $clave = 'filas'): array
    {
        $ruta = preg_replace('#^storage/app/#', '', $ruta);
        if (! Storage::disk('local')->exists($ruta)) {
            return [null, null, "No existe storage/app/{$ruta}."];
        }
        $json = Storage::disk('local')->get($ruta);
        $datos = json_decode((string) $json, true);
        if (! is_array($datos) || ! isset($datos[$clave]) || ! is_array($datos[$clave])) {
            return [null, null, 'El archivo no tiene el formato esperado.'];
        }

        return [$datos, $json, null];
    }

    private static function lista($valor): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $valor)), fn ($v) => $v !== '' && $v !== '*')));
    }

    private static function fecha($valor): ?Carbon
    {
        $valor = trim((string) $valor);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
    }

    private function fallar(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
