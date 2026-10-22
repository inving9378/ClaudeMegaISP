<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingConfig;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * FAC-08 (item #515, padre #482): reactivación GRADUAL de los envíos automáticos de cobro. Es una
 * segunda compuerta, DETRÁS de la pausa general (billing_config.envio_pausado, que no se toca):
 * aunque se quite la pausa, solo sale lo que la etapa permite.
 *
 * Etapas (config billing.reactivacion.etapa), de menos a más:
 *  - cerrado (default): no sale nada automático. Lista vacía = cero.
 *  - piloto: solo los client_id de clientes_piloto.
 *  - zona: piloto + clientes de las colonias / municipios listados (no hay campo «zona» en el
 *    sistema; se usa client_main_information.colony_id / municipality_id).
 *  - todos.
 * Más límites: por corrida, por día y parada por errores. WhatsApp tiene su propia aprobación
 * (aprobar correo no activa WhatsApp).
 *
 * Desde 2026-10-22 la etapa se cambia en Configuración › Finanzas › «Envíos automáticos» y se
 * guarda en billing_config (reactivacion_nivel): cerrado → baja (todos los clientes, N por corrida
 * y M por día; 20 y 100 de inicio) → normal (todos, sin tope de etapa: solo el --limit de cada
 * corrida). Si nunca se cambió ahí, manda el .env como antes (BILLING_REACTIVACION_*).
 *
 * No aplica al «Enviar» manual de la pantalla (acción humana, con confirmación, uno a uno).
 */
class ReactivacionEnvios
{
    public const CERRADO = 'cerrado';
    public const PILOTO = 'piloto';
    public const ZONA = 'zona';
    public const TODOS = 'todos';

    private const ETAPAS = [self::CERRADO, self::PILOTO, self::ZONA, self::TODOS];

    /** Niveles de la pantalla «Envíos automáticos» (billing_config.reactivacion_nivel). */
    public const NIVEL_CERRADO = 'cerrado';
    public const NIVEL_BAJA = 'baja';
    public const NIVEL_NORMAL = 'normal';
    public const NIVELES = [self::NIVEL_CERRADO, self::NIVEL_BAJA, self::NIVEL_NORMAL];
    public const BAJA_POR_CORRIDA = 20;
    public const BAJA_POR_DIA = 100;

    private array $cfg;

    /** @var Closure(int): array{colonia: ?int, municipio: ?int} */
    private Closure $ubicacion;

    private array $cacheUbicacion = [];

    public function __construct(?array $config = null, ?Closure $ubicacion = null)
    {
        $this->cfg = $config ?? self::configVigente();
        $this->ubicacion = $ubicacion ?? function (int $clientId): array {
            $cmi = DB::table('client_main_information')->where('client_id', $clientId)->first(['colony_id', 'municipality_id']);

            return ['colonia' => $cmi?->colony_id ? (int) $cmi->colony_id : null, 'municipio' => $cmi?->municipality_id ? (int) $cmi->municipality_id : null];
        };
    }

    /**
     * La config de billing.reactivacion con lo que se haya elegido en la pantalla encima. Sin nivel
     * guardado (o sin la columna aún) queda igual que el .env.
     */
    public static function configVigente(): array
    {
        $cfg = (array) config('billing.reactivacion', []);
        $cfg['nivel'] = null;
        try {
            $bc = BillingConfig::current();
        } catch (\Throwable) {
            return $cfg;
        }
        $nivel = $bc->reactivacion_nivel;
        if (! in_array($nivel, self::NIVELES, true)) {
            return $cfg;
        }

        $cfg['nivel'] = $nivel;
        $cfg['etapa'] = $nivel === self::NIVEL_CERRADO ? self::CERRADO : self::TODOS;
        if ($nivel === self::NIVEL_BAJA) {
            $cfg['limite_por_corrida'] = (int) ($bc->reactivacion_limite_corrida ?: self::BAJA_POR_CORRIDA);
            $cfg['limite_diario'] = (int) ($bc->reactivacion_limite_diario ?: self::BAJA_POR_DIA);
        }
        $cfg['sin_tope'] = $nivel === self::NIVEL_NORMAL;

        return $cfg;
    }

    /** cerrado | baja | normal si se eligió en la pantalla; null si manda el .env. */
    public function nivel(): ?string
    {
        return $this->cfg['nivel'] ?? null;
    }

    /** En «normal» la etapa no pone tope (queda el --limit de cada corrida y la parada por errores). */
    public function sinTope(): bool
    {
        return ! empty($this->cfg['sin_tope']) && $this->etapa() !== self::CERRADO;
    }

    public function etapa(): string
    {
        $etapa = strtolower(trim((string) ($this->cfg['etapa'] ?? self::CERRADO)));

        return in_array($etapa, self::ETAPAS, true) ? $etapa : self::CERRADO;
    }

    /** @return array{0: bool, 1: string} [permitido, motivo] */
    public function permiteCliente(?int $clientId): array
    {
        $etapa = $this->etapa();
        if ($etapa === self::CERRADO) {
            return [false, 'reactivación cerrada'];
        }
        if ($etapa === self::TODOS) {
            return [true, 'etapa todos'];
        }
        if (! $clientId) {
            return [false, 'sin cliente'];
        }
        if (in_array($clientId, self::ids($this->cfg['clientes_piloto'] ?? []), true)) {
            return [true, 'cliente piloto'];
        }
        if ($etapa === self::ZONA) {
            $u = $this->cacheUbicacion[$clientId] ??= ($this->ubicacion)($clientId);
            if ($u['colonia'] && in_array($u['colonia'], self::ids($this->cfg['colonias'] ?? []), true)) {
                return [true, 'colonia de la zona'];
            }
            if ($u['municipio'] && in_array($u['municipio'], self::ids($this->cfg['municipios'] ?? []), true)) {
                return [true, 'municipio de la zona'];
            }
        }

        return [false, "fuera de la etapa {$etapa}"];
    }

    /** Envíos que aún caben: el menor entre lo que queda de la corrida y lo que queda del día. */
    public function cupo(int $enviadosEnLaCorrida, int $enviadosHoy): int
    {
        if ($this->sinTope()) {
            return PHP_INT_MAX;
        }
        $porCorrida = max(0, (int) ($this->cfg['limite_por_corrida'] ?? 0) - $enviadosEnLaCorrida);
        $porDia = max(0, (int) ($this->cfg['limite_diario'] ?? 0) - $enviadosHoy);

        return min($porCorrida, $porDia);
    }

    /** ¿Parar la corrida por errores? */
    public function pararPorErrores(int $errores): bool
    {
        return $errores >= max(1, (int) ($this->cfg['max_errores_por_corrida'] ?? 1));
    }

    /** WhatsApp necesita su propia aprobación además del flag de respaldo que ya existía. */
    public function permiteWhatsApp(): bool
    {
        return $this->etapa() !== self::CERRADO && ! empty($this->cfg['whatsapp']) && (bool) config('billing.whatsapp_fallback_enabled');
    }

    /** @return int[] */
    public static function ids($valor): array
    {
        $lista = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_unique(array_filter(array_map(fn ($v) => (int) trim((string) $v), $lista), fn ($v) => $v > 0)));
    }

    public function resumenConfig(): array
    {
        return [
            'etapa'                   => $this->etapa(),
            'nivel'                   => $this->nivel(),
            'origen'                  => $this->nivel() ? 'pantalla' : 'env',
            'sin_tope'                => $this->sinTope(),
            'clientes_piloto'         => count(self::ids($this->cfg['clientes_piloto'] ?? [])),
            'colonias'                => count(self::ids($this->cfg['colonias'] ?? [])),
            'municipios'              => count(self::ids($this->cfg['municipios'] ?? [])),
            'limite_por_corrida'      => (int) ($this->cfg['limite_por_corrida'] ?? 0),
            'limite_diario'           => (int) ($this->cfg['limite_diario'] ?? 0),
            'max_errores_por_corrida' => (int) ($this->cfg['max_errores_por_corrida'] ?? 0),
            'whatsapp'                => $this->permiteWhatsApp(),
        ];
    }
}
