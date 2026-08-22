<?php

namespace App\Services\Ipv6;

use App\Models\Ipv6DualStackCutoffDryRun;
use Illuminate\Support\Facades\DB;

/**
 * Item #1047 (IPv6 Fase 3.1a, sub-item de #991) — orquestador PURO/dry-run
 * del corte dual-stack (IPv4+IPv6) por cliente. Reusa Ipv6CommandGenerator/
 * Drivers (#950/#951) para el lado IPv6 (crearListaFirewall sobre
 * MgNet_Morosos_V6); el lado IPv4 replica el MISMO patrón de comando CLI
 * pero sobre /ip (no existe un driver v4 "puro" que reusar aquí) usando
 * exactamente los mismos campos que ya usa el precedente real
 * MikrotikCreateAddressList.php:57 (list=MgNet_Morosos, address, comment).
 *
 * Alcance duro (igual que MikrotikIpv6Client.php:14-20): este servicio NUNCA
 * abre conexión a un router real ni toca MikrotikCreateAddressList.php /
 * MikrotikRulesJob.php:241-311 (precedente IPv4-only, se deja intacto). Solo
 * genera texto y lo registra en `ipv6_dual_stack_cutoff_dry_runs` — deja
 * lista la pieza de software para cuando #953 (Fase 2, delegación IPv6 a
 * clientes) aporte el prefijo real por cliente; hoy $ipv6Prefix puede ser
 * sintético/de prueba. El corte real contra el router de borde es #1048
 * (bloqueado, requiere Irving) — fuera de alcance aquí.
 */
class Ipv6DualStackCutoffOrchestrator
{
    public const LISTA_V4 = 'MgNet_Morosos';
    public const LISTA_V6 = 'MgNet_Morosos_V6';

    /**
     * Genera el par de comandos dry-run (IPv4 + IPv6). Método PURO: no toca
     * BD ni router, misma entrada siempre produce la misma salida.
     *
     * @return array{driver: string, comando_ipv4: string, comando_ipv6: ?string, comandos: string[], advertencias: string[]}
     */
    public function generarComandos(string $ipv4, string $ipv6Prefix, string $version, string $comentario): array
    {
        $this->validarIpv4($ipv4);
        $this->validarPrefijoIpv6($ipv6Prefix);

        $driver = (new Ipv6CommandGenerator())->driverParaVersion($version);
        $comentarioEscapado = str_replace('"', '\\"', $comentario);

        $comandoIpv4 = sprintf(
            '/ip firewall address-list add list=%s address=%s comment="%s"',
            self::LISTA_V4,
            $ipv4,
            $comentarioEscapado
        );

        $resultadoV6 = $driver->crearListaFirewall(self::LISTA_V6, $ipv6Prefix, $comentario);
        $comandoIpv6 = $resultadoV6['comandos'][0] ?? null;

        return [
            'driver' => $driver->nombre(),
            'comando_ipv4' => $comandoIpv4,
            'comando_ipv6' => $comandoIpv6,
            'comandos' => $comandoIpv6 !== null ? [$comandoIpv4, $comandoIpv6] : [$comandoIpv4],
            'advertencias' => $resultadoV6['advertencias'],
        ];
    }

    /**
     * Orquesta: genera el par de comandos y registra la intención del corte
     * dual-stack en la tabla de auditoría dentro de una transacción. NUNCA
     * llama al router real.
     *
     * @throws \InvalidArgumentException si IPv4/prefijo IPv6 son inválidos
     */
    public function orquestar(int $clientId, string $ipv4, string $ipv6Prefix, string $version, ?string $comentario = null): Ipv6DualStackCutoffDryRun
    {
        $comentario ??= "MgNet-IPv6 dry-run cliente #{$clientId}";

        $resultado = $this->generarComandos($ipv4, $ipv6Prefix, $version, $comentario);

        return DB::transaction(function () use ($clientId, $ipv4, $ipv6Prefix, $version, $comentario, $resultado) {
            return Ipv6DualStackCutoffDryRun::create([
                'client_id' => $clientId,
                'ipv4' => $ipv4,
                'ipv6_prefix' => $ipv6Prefix,
                'router_version' => $version,
                'driver' => $resultado['driver'],
                'comentario' => $comentario,
                'comandos' => $resultado['comandos'],
                'advertencias' => $resultado['advertencias'],
                'created_at' => now(),
            ]);
        });
    }

    private function validarIpv4(string $ipv4): void
    {
        if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new \InvalidArgumentException("IPv4 mal formada: {$ipv4}");
        }
    }

    private function validarPrefijoIpv6(string $prefijo): void
    {
        if (!str_contains($prefijo, '/')) {
            throw new \InvalidArgumentException("Prefijo IPv6 inválido (falta '/longitud'): {$prefijo}");
        }

        [$direccion, $longitud] = explode('/', $prefijo, 2);

        if (!ctype_digit($longitud) || (int) $longitud < 1 || (int) $longitud > 128) {
            throw new \InvalidArgumentException("Longitud de prefijo IPv6 inválida: {$prefijo}");
        }

        if (filter_var($direccion, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new \InvalidArgumentException("Prefijo IPv6 mal formado: {$prefijo}");
        }
    }
}
