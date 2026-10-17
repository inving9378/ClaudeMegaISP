<?php

namespace App\Services\Finance\Billing;

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
 * (aprobar correo no activa WhatsApp). Avanzar o retroceder de etapa = cambiar la config (Irving).
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

    private array $cfg;

    /** @var Closure(int): array{colonia: ?int, municipio: ?int} */
    private Closure $ubicacion;

    private array $cacheUbicacion = [];

    public function __construct(?array $config = null, ?Closure $ubicacion = null)
    {
        $this->cfg = $config ?? (array) config('billing.reactivacion', []);
        $this->ubicacion = $ubicacion ?? function (int $clientId): array {
            $cmi = DB::table('client_main_information')->where('client_id', $clientId)->first(['colony_id', 'municipality_id']);

            return ['colonia' => $cmi?->colony_id ? (int) $cmi->colony_id : null, 'municipio' => $cmi?->municipality_id ? (int) $cmi->municipality_id : null];
        };
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
