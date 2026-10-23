<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingConfig;
use App\Models\User;
use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Services\Finance\Invoice\ProformaAnticipada;
use App\Support\Seguridad\SuperAdministrador;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * #581 (2026-10-22): interruptor de la proforma anticipada, en Configuración › Finanzas › «Envíos
 * automáticos». Vive en billing_config.proforma_anticipada; BILLING_PROFORMA_ANTICIPADA del .env solo
 * dio el valor inicial al migrar.
 *  - encendida: invoice:create-proformas genera la proforma de cada cliente Activo N días antes de
 *    su día de facturación (N = el suyo o el general, billing_config.envio_prefactura_dias);
 *  - apagada: la regla de siempre, todas el día 25 del mes anterior.
 * Generar la proforma no la envía: eso depende de «Avisos de cobro automáticos».
 *
 * Encender y apagar: solo super-administrador. Los días también se cambian desde Notificaciones
 * Finanzas › Facturas proforma, con el permiso de esa pantalla. Todo cambio queda en activity_log
 * (log_name facturacion-envios) y en billing_config con quién y cuándo.
 */
final class InterruptorProformaAnticipada
{
    public const PALABRA = 'ENCENDER';

    private static ?bool $hayColumna = null;

    /** Lo que lee invoice:create-proformas. Antes de migrar (no existe la columna), el .env, como hasta hoy. */
    public static function encendida(): bool
    {
        self::$hayColumna ??= Schema::hasColumn('billing_config', 'proforma_anticipada');

        return self::$hayColumna
            ? (bool) BillingConfig::current()->proforma_anticipada
            : (bool) config('billing.proforma_anticipada');
    }

    /** BILLING_PROFORMA_ANTICIPADA → valor inicial de la columna (solo al migrar). */
    public static function valorInicial($env): bool
    {
        return (bool) $env;
    }

    /** Solo para pruebas. */
    public static function olvidar(): void
    {
        self::$hayColumna = null;
    }

    public function estado(): array
    {
        $config = BillingConfig::current();
        $propios = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias')
            ? DB::table('billing_configurations')->whereNotNull('envio_prefactura_dias')
                ->selectRaw('count(*) as n, min(envio_prefactura_dias) as minimo, max(envio_prefactura_dias) as maximo')->first()
            : null;
        $creacion = DB::table('config_finance_notifications')->where('type_config', 'proforma_invoice')->orderBy('id')->value('auto_send_notifications');
        $porEstado = DB::table('client_main_information')->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');

        return [
            'encendido' => self::encendida(),
            'cambiado_por' => $this->nombre($config->proforma_anticipada_cambiada_por),
            'cambiado_at' => $config->proforma_anticipada_cambiada_at?->toIso8601String(),
            'dias' => EnvioPrefactura::diasPorDefecto(),
            'max_dias' => ProformaAnticipada::MAX_DIAS,
            'dias_cambiados_por' => $this->nombre($config->envio_prefactura_dias_cambiado_por),
            'dias_cambiados_at' => $config->envio_prefactura_dias_cambiado_at?->toIso8601String(),
            'clientes_con_valor_propio' => (int) ($propios->n ?? 0),
            'rango_propio' => ($propios->n ?? 0) ? [(int) $propios->minimo, (int) $propios->maximo] : null,
            'creacion_automatica' => $creacion === null ? null : (bool) $creacion,
            'estado_cuenta_mensual' => (bool) config('billing.estado_cuenta_mensual'),
            'clientes_activos' => (int) ($porEstado[ReglasFacturacion::ACTIVO] ?? 0),
            // Con la regla de siempre reciben proforma todos menos los Cancelados; encendida, solo los Activos.
            'clientes_sin_proforma_al_encender' => (int) $porEstado->except([ReglasFacturacion::ACTIVO, ReglasFacturacion::CANCELADO])->sum(),
        ];
    }

    /** Encender, con los días de anticipo que se confirman en el mismo diálogo. */
    public function encender(User $user, int $dias): array
    {
        $this->exigirPermiso($user);
        $this->validarDias($dias);

        return DB::transaction(function () use ($user, $dias) {
            $config = $this->bloquear();
            if ($config->proforma_anticipada) {
                throw new InvalidArgumentException('La proforma anticipada ya está encendida.');
            }
            $diasAntes = $this->diasDe($config);
            $campos = ['proforma_anticipada' => true, 'proforma_anticipada_cambiada_por' => $user->id, 'proforma_anticipada_cambiada_at' => now()];
            if ($diasAntes !== $dias) {
                $campos += $this->camposDias($dias, $user);
            }
            $config->forceFill($campos)->save();
            $this->auditar($user, $config, 'Proforma anticipada encendida', ['antes' => 'apagada', 'despues' => 'encendida',
                'dias_antes' => $diasAntes, 'dias' => $dias]);

            return ['message' => "Proforma anticipada encendida: desde la próxima corrida (03:00) cada cliente Activo la recibe {$dias} "
                . ($dias === 1 ? 'día' : 'días') . ' antes de su día de facturación (o los suyos, si tiene).'];
        });
    }

    /** Apagar: confirmación simple. Vuelve la regla del día 25 desde la próxima corrida; los días quedan guardados. */
    public function apagar(User $user): array
    {
        $this->exigirPermiso($user);

        return DB::transaction(function () use ($user) {
            $config = $this->bloquear();
            if (! $config->proforma_anticipada) {
                throw new InvalidArgumentException('La proforma anticipada ya está apagada.');
            }
            $config->forceFill(['proforma_anticipada' => false, 'proforma_anticipada_cambiada_por' => $user->id,
                'proforma_anticipada_cambiada_at' => now()])->save();
            $this->auditar($user, $config, 'Proforma anticipada apagada', ['antes' => 'encendida', 'despues' => 'apagada', 'dias' => $this->diasDe($config)]);

            return ['message' => 'Proforma anticipada apagada: desde la próxima corrida las proformas vuelven a generarse el día 25 del mes anterior.'];
        });
    }

    /**
     * Días de anticipo (valor general). El permiso lo revisa quien llama: «Envíos automáticos» exige
     * super-administrador; Notificaciones Finanzas, el permiso de su pantalla. $origen va a la auditoría.
     */
    public function cambiarDias(User $user, int $dias, string $origen): array
    {
        $this->validarDias($dias);

        return DB::transaction(function () use ($user, $dias, $origen) {
            $config = $this->bloquear();
            $antes = $this->diasDe($config);
            if ($antes === $dias) {
                return ['message' => "Sin cambios: ya son {$dias} días.", 'cambio' => false];
            }
            $config->forceFill($this->camposDias($dias, $user))->save();
            $this->auditar($user, $config, 'Días de anticipo de la proforma cambiados', ['antes' => $antes, 'despues' => $dias,
                'origen' => $origen, 'proforma_anticipada' => (bool) $config->proforma_anticipada ? 'encendida' : 'apagada']);

            return ['message' => "Días de anticipo: {$dias}." . ($config->proforma_anticipada ? ' Se aplica desde la próxima corrida.' : ''), 'cambio' => true];
        });
    }

    public static function puedeGestionar(?User $user): bool
    {
        return SuperAdministrador::es($user);
    }

    private function exigirPermiso(User $user): void
    {
        if (! self::puedeGestionar($user)) {
            throw new AuthorizationException('Solo un super-administrador puede encender o apagar la proforma anticipada.');
        }
    }

    private function validarDias(int $dias): void
    {
        if ($dias < 0 || $dias > ProformaAnticipada::MAX_DIAS) {
            throw new InvalidArgumentException('Los días de anticipo van de 0 a ' . ProformaAnticipada::MAX_DIAS . '.');
        }
    }

    /** Días guardados en esta fila, o el valor por defecto si no son válidos (igual que EnvioPrefactura). */
    private function diasDe(BillingConfig $config): int
    {
        return ProformaAnticipada::dias($config->envio_prefactura_dias) ?? EnvioPrefactura::DIAS_POR_DEFECTO;
    }

    private function camposDias(int $dias, User $user): array
    {
        return ['envio_prefactura_dias' => $dias, 'envio_prefactura_dias_cambiado_por' => $user->id, 'envio_prefactura_dias_cambiado_at' => now()];
    }

    private function bloquear(): BillingConfig
    {
        return BillingConfig::whereKey(BillingConfig::current()->id)->lockForUpdate()->firstOrFail();
    }

    private function auditar(User $user, BillingConfig $config, string $descripcion, array $detalle): void
    {
        activity(EnviosAutomaticos::LOG)->causedBy($user)->performedOn($config)->withProperties($detalle)->log($descripcion);
    }

    private function nombre($id): ?string
    {
        $user = $id ? User::find($id) : null;

        return $user ? trim($user->name . ' ' . $user->father_last_name) : null;
    }
}
