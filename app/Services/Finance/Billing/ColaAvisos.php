<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * «Ver detalle de la cola» (#579) en Configuración › Finanzas › Envíos automáticos: la lista de
 * billing_notifications con filtros, y cancelar o reactivar lo seleccionado (filas sueltas o todos
 * los del filtro). Solo super-administrador.
 *
 * Cancelar y reactivar dejan el mismo archivo que revierte `billing:cancelar-colas --revertir=…
 * --ejecutar` y quedan en activity_log (facturacion-envios). Reactivar no manda nada por sí mismo:
 * lo vuelve «en espera» y sale con el envío automático, la pausa general y la etapa vigentes.
 * Lo enviado no se toca, y un ticket no se reactiva: es solo para imprimir en caja (#580).
 */
class ColaAvisos
{
    public const POR_PAGINA = 50;

    public const CANCELAR = 'cancelar';
    public const REACTIVAR = 'reactivar';
    public const ACCIONES = [self::CANCELAR, self::REACTIVAR];

    public const ESTADOS = [
        BillingNotification::STATUS_WAITING   => 'En espera',
        BillingNotification::STATUS_SENT      => 'Enviado',
        BillingNotification::STATUS_CANCELLED => 'Cancelado',
        BillingNotification::STATUS_ERROR     => 'Error',
    ];

    /** Desde qué estados se puede aplicar cada acción. */
    private const DESDE = [
        self::CANCELAR  => [BillingNotification::STATUS_WAITING, BillingNotification::STATUS_ERROR],
        self::REACTIVAR => [BillingNotification::STATUS_CANCELLED, BillingNotification::STATUS_ERROR],
    ];

    public const MAX_IDS = 5000;
    public const MAX_HORAS = EnviosAutomaticos::DEPURAR_MAX_HORAS;

    public function __construct(private EnviosAutomaticos $envios)
    {
    }

    /** Una página (50) de la cola con el filtro, de lo más nuevo a lo más viejo. */
    public function listar(array $filtro, int $pagina = 1): array
    {
        $f = $this->normalizar($filtro);
        $total = $this->consulta($f)->count();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, $pagina), $paginas);
        $whatsapp = app(ReactivacionEnvios::class)->permiteWhatsApp();

        $filas = $this->consulta($f)->toBase()
            ->select(['id', 'client_id', 'document_type', 'invoice_id', 'payment_id', 'email', 'status', 'send_by_email', 'created_at', 'enviado_at', 'error_log'])
            ->selectSub(fn ($q) => $q->from('client_main_information')->whereColumn('client_main_information.client_id', 'billing_notifications.client_id')
                ->selectRaw("TRIM(CONCAT_WS(' ', name, father_last_name, mother_last_name))")->limit(1), 'cliente')
            ->selectSub(fn ($q) => $q->from('client_main_information')->whereColumn('client_main_information.client_id', 'billing_notifications.client_id')
                ->selectRaw("COALESCE(NULLIF(phone, ''), NULLIF(phone2, ''), NULLIF(phone3, '')) IS NOT NULL")->limit(1), 'tiene_telefono')
            ->selectSub(fn ($q) => $q->from('invoices')->whereColumn('invoices.id', 'billing_notifications.invoice_id')->select('total')->limit(1), 'monto_factura')
            ->selectSub(fn ($q) => $q->from('payments')->whereColumn('payments.id', 'billing_notifications.payment_id')->select('amount')->limit(1), 'monto_pago')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->forPage($pagina, self::POR_PAGINA)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'tipo' => $r->document_type,
                'cliente_id' => (int) $r->client_id,
                'cliente' => $r->cliente ?: null,
                'monto' => $r->document_type === BillingNotification::TYPE_PREFACTURA ? $this->numero($r->monto_factura) : $this->numero($r->monto_pago),
                'canal' => $this->canal($r, $whatsapp),
                'creado' => Carbon::parse($r->created_at)->toIso8601String(),
                'estado' => $r->status,
                'enviado_at' => $r->enviado_at ? Carbon::parse($r->enviado_at)->toIso8601String() : null,
                'nota' => $r->error_log ? mb_substr($r->error_log, 0, 160) : null,
            ])->all();

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => self::POR_PAGINA, 'filtro' => $f];
    }

    /**
     * Lo que pasaría al aplicar $accion a la selección, sin tocar nada: cuántos se pueden cambiar
     * (por tipo), cuántos de lo seleccionado no aplican, cuántos tienen correo y cómo están los envíos.
     *
     * @param  array{ids?: int[], filtro?: array}  $seleccion
     */
    public function conteo(string $accion, array $seleccion): array
    {
        $this->validarAccion($accion);
        $seleccionados = $this->seleccion($seleccion)->count();
        $objetivo = $this->objetivo($accion, $seleccion);
        $porTipo = (clone $objetivo)->toBase()->selectRaw('document_type, count(*) as n')->groupBy('document_type')->pluck('n', 'document_type');
        $total = (int) $porTipo->sum();
        $conCorreo = (clone $objetivo)->where('send_by_email', true)->whereNotNull('email')->where('email', '!=', '')->count();
        $tipos = [];
        foreach (EnviosAutomaticos::TIPOS as $tipo => $nombre) {
            $tipos[] = ['tipo' => $tipo, 'nombre' => $nombre, 'n' => (int) ($porTipo[$tipo] ?? 0)];
        }

        return [
            'accion' => $accion,
            'seleccionados' => $seleccionados,
            'total' => $total,
            'no_aplican' => max(0, $seleccionados - $total),
            'tipos' => $tipos,
            'con_correo' => $conCorreo,
            'pausado' => ! $this->envios->encendido(),
            'etapa' => $this->envios->etapa()['ritmo_nombre'] ?? null,
        ];
    }

    /**
     * Cancela o reactiva la selección. $esperado es el conteo que vio la persona: si cambió, no se
     * toca nada y se avisa con el nuevo.
     *
     * @param  array{ids?: int[], filtro?: array}  $seleccion
     */
    public function aplicar(User $user, string $accion, array $seleccion, int $esperado): array
    {
        $this->envios->exigirPermiso($user);
        $this->validarAccion($accion);

        return DB::transaction(function () use ($user, $accion, $seleccion, $esperado) {
            $objetivo = $this->objetivo($accion, $seleccion);
            $actual = (clone $objetivo)->lockForUpdate()->count();
            if ($actual !== $esperado) {
                throw new InvalidArgumentException("La selección cambió: ahora son {$actual}, no {$esperado}. Revisa el conteo y confirma de nuevo.");
            }
            if ($actual === 0) {
                throw new InvalidArgumentException($accion === self::CANCELAR
                    ? 'No hay nada que cancelar en lo seleccionado: solo se cancela lo que está en espera o con error.'
                    : 'No hay nada que reactivar en lo seleccionado: solo se reactiva lo cancelado o con error, y nunca un ticket.');
            }

            $quien = trim($user->name . ' ' . $user->father_last_name);
            $cancelar = $accion === self::CANCELAR;
            [$n, $archivo, $porTipo] = $this->envios->cambiarEstado(
                $objetivo,
                $cancelar ? BillingNotification::STATUS_CANCELLED : BillingNotification::STATUS_WAITING,
                ($cancelar ? 'Cancelada' : 'Reactivada') . " a mano desde «Ver detalle de la cola» por {$quien}.",
                'detalle-' . $accion,
            );
            $config = BillingConfig::current();
            $detalle = ['accion' => $accion, 'cambiadas' => $n, 'por_tipo' => $porTipo, 'archivo_para_revertir' => $archivo,
                'seleccion' => isset($seleccion['ids']) ? ['ids' => count($seleccion['ids'])] : ['filtro' => $this->normalizar($seleccion['filtro'] ?? [])],
                'pausado' => ! $this->envios->encendido($config)];
            $this->envios->auditar($user, $config, $cancelar ? 'Avisos cancelados desde el detalle de la cola' : 'Avisos reactivados desde el detalle de la cola', $detalle);

            $mensaje = $cancelar
                ? "Se cancelaron {$n} avisos (no se borran; se pueden revertir)."
                : "Se reactivaron {$n} avisos: vuelven a «en espera»" . (! $this->envios->encendido($config) ? ' y no saldrán mientras los envíos sigan apagados.' : ' y salen con el próximo envío automático.');

            return ['message' => $mensaje] + $detalle;
        });
    }

    /** Filtro limpio: solo valores válidos; lo demás se ignora. */
    public function normalizar(array $filtro): array
    {
        $fecha = function ($v) {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('!Y-m-d', $v)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        };
        $horas = isset($filtro['horas']) && is_numeric($filtro['horas']) ? (int) $filtro['horas'] : null;
        $cliente = trim((string) ($filtro['cliente'] ?? ''));

        return [
            'tipo' => array_key_exists($filtro['tipo'] ?? '', EnviosAutomaticos::TIPOS) ? $filtro['tipo'] : null,
            'estado' => array_key_exists($filtro['estado'] ?? '', self::ESTADOS) ? $filtro['estado'] : null,
            'horas' => $horas !== null && $horas >= 1 && $horas <= self::MAX_HORAS ? $horas : null,
            'desde' => $fecha($filtro['desde'] ?? null),
            'hasta' => $fecha($filtro['hasta'] ?? null),
            'cliente' => $cliente !== '' ? mb_substr($cliente, 0, 80) : null,
        ];
    }

    private function consulta(array $f): Builder
    {
        return BillingNotification::query()
            ->when($f['tipo'], fn ($q, $v) => $q->where('document_type', $v))
            ->when($f['estado'], fn ($q, $v) => $q->where('status', $v))
            ->when($f['horas'], fn ($q, $v) => $q->where('created_at', '<', now()->subHours($v)))
            ->when($f['desde'], fn ($q, $v) => $q->where('created_at', '>=', $v . ' 00:00:00'))
            ->when($f['hasta'], fn ($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->when($f['cliente'], function ($q, $v) {
                if (ctype_digit(ltrim($v, '#'))) {
                    return $q->where('client_id', (int) ltrim($v, '#'));
                }
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v) . '%';

                return $q->whereIn('client_id', fn ($s) => $s->from('client_main_information')->select('client_id')
                    ->whereRaw("CONCAT_WS(' ', name, father_last_name, mother_last_name) LIKE ?", [$like]));
            });
    }

    /** Lo seleccionado tal cual: filas sueltas o todos los del filtro. */
    private function seleccion(array $seleccion): Builder
    {
        if (array_key_exists('ids', $seleccion)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $seleccion['ids']), fn ($v) => $v > 0)));
            if (! $ids || count($ids) > self::MAX_IDS) {
                throw new InvalidArgumentException('Selecciona entre 1 y ' . self::MAX_IDS . ' avisos, o usa «todos los del filtro».');
            }

            return BillingNotification::query()->whereIn('id', $ids);
        }
        if (! isset($seleccion['filtro']) || ! is_array($seleccion['filtro'])) {
            throw new InvalidArgumentException('Falta la selección.');
        }

        return $this->consulta($this->normalizar($seleccion['filtro']));
    }

    /** De lo seleccionado, lo que la acción puede cambiar. */
    private function objetivo(string $accion, array $seleccion): Builder
    {
        return $this->seleccion($seleccion)
            ->whereIn('status', self::DESDE[$accion])
            ->when($accion === self::REACTIVAR, fn ($q) => $q->where('document_type', '!=', BillingNotification::TYPE_TICKET));
    }

    /** caja (ticket, #580) · correo · whatsapp (respaldo sin correo) · ninguno */
    private function canal(object $r, bool $whatsapp): string
    {
        if ($r->document_type === BillingNotification::TYPE_TICKET) {
            return 'caja';
        }
        if ($r->send_by_email && trim((string) $r->email) !== '') {
            return 'correo';
        }

        return $whatsapp && $r->tiene_telefono ? 'whatsapp' : 'ninguno';
    }

    private function numero($v): ?float
    {
        return $v === null ? null : round((float) $v, 2);
    }

    private function validarAccion(string $accion): void
    {
        if (! in_array($accion, self::ACCIONES, true)) {
            throw new InvalidArgumentException('Acción desconocida.');
        }
    }
}
