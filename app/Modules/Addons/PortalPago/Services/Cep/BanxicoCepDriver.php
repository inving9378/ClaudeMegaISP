<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Valida un pago contra el servicio público de CEP de Banxico.
 *
 * ⚠️ El endpoint NO es una API REST documentada: es el formulario web
 * https://www.banxico.org.mx/cep/valida.do (POST). Devuelve XML cuando el pago
 * existe y está liquidado; HTML/vacío cuando no. Banxico puede cambiar este
 * contrato, aplicar rate-limit o protección anti-bot SIN aviso.
 *
 * Por eso este driver es defensivo al extremo: timeouts cortos, UN solo
 * reintento, y ante CUALQUIER falla (red, no-200, XML inválido, sin nodo de
 * liquidación) devuelve inconclusive() — NUNCA lanza una excepción que tumbe
 * el flujo de conciliación. El reporte queda en pendiente_validacion.
 */
class BanxicoCepDriver implements CepValidatorDriver
{
    private string $endpoint;
    private int $connectTimeout;
    private int $readTimeout;

    /** ms de espera antes del único reintento. */
    private const RETRY_SLEEP_MS = 2000;
    private const MAX_ATTEMPTS   = 2; // intento original + 1 reintento

    public function __construct(?string $endpoint = null, ?int $connectTimeout = null, ?int $readTimeout = null)
    {
        $this->endpoint       = $endpoint       ?? (string) config('pagos.cep_endpoint', 'https://www.banxico.org.mx/cep/valida.do');
        $this->connectTimeout = $connectTimeout ?? (int) config('pagos.cep_timeout_connect', 5);
        $this->readTimeout    = $readTimeout    ?? (int) config('pagos.cep_timeout_read', 10);
    }

    public function name(): string
    {
        return 'banxico';
    }

    public function validate(CepQuery $query): CepValidationResult
    {
        $response = $this->request($query);

        if ($response === null) {
            return CepValidationResult::inconclusive(
                'Sin respuesta de Banxico tras el reintento (timeout o red).',
                ['driver' => 'banxico', 'query' => $query->toLogContext()]
            );
        }

        if ($response->status() !== 200) {
            return CepValidationResult::inconclusive(
                "Banxico respondió HTTP {$response->status()}.",
                ['driver' => 'banxico', 'http_status' => $response->status()]
            );
        }

        $body = (string) $response->body();
        if (trim($body) === '') {
            return CepValidationResult::inconclusive('Respuesta vacía de Banxico.', ['driver' => 'banxico']);
        }

        // Banxico devuelve HTML/texto cuando el pago no existe.
        if ($this->looksLikeNotFound($body)) {
            return CepValidationResult::notFound(
                'Banxico no encontró un pago con esa clave de rastreo.',
                null,
                ['driver' => 'banxico', 'snippet' => mb_substr(strip_tags($body), 0, 240)]
            );
        }

        $xml = $this->parseXml($body);
        if ($xml === null) {
            return CepValidationResult::inconclusive(
                'La respuesta de Banxico no es XML parseable.',
                ['driver' => 'banxico', 'snippet' => mb_substr(strip_tags($body), 0, 240)]
            );
        }

        $nodo = $this->findLiquidationNode($xml);
        if ($nodo === null) {
            return CepValidationResult::inconclusive(
                'El XML de Banxico no contiene el nodo de liquidación esperado.',
                ['driver' => 'banxico']
            );
        }

        $estado = $this->extractEstado($xml) ?? 'LIQUIDADO';
        $monto  = $this->extractAttr($nodo, ['MontoPago', 'Monto', 'monto']);
        $clabe  = $this->extractAttr($nodo, ['Cuenta', 'CuentaBeneficiario', 'cuentaBeneficiario', 'CuentaBeneficiaria']);

        $raw = [
            'driver'                 => 'banxico',
            'cep_estado'             => $estado,
            'cep_monto'              => $monto,
            'cep_clabe_beneficiaria' => $clabe,
        ];

        if (strtoupper(trim($estado)) !== 'LIQUIDADO') {
            return CepValidationResult::notFound("Estado de CEP = {$estado} (no LIQUIDADO).", $estado, $raw);
        }

        return CepValidationResult::found($estado, $monto, $clabe, $body, $raw);
    }

    /**
     * POST al formulario de Banxico con UN solo reintento. Cualquier excepción
     * de red se traga aquí y se devuelve null (→ inconclusive aguas arriba).
     */
    private function request(CepQuery $query): ?Response
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return Http::connectTimeout($this->connectTimeout)
                    ->timeout($this->readTimeout)
                    ->withHeaders([
                        'User-Agent' => 'MegaISP-PortalPago/1.0',
                        'Accept'     => 'application/xml,text/xml,*/*',
                    ])
                    ->asForm()
                    ->post($this->endpoint, $query->toFormParams());
            } catch (\Throwable $e) {
                Log::warning('BanxicoCepDriver: intento de red falló', [
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                    'query'   => $query->toLogContext(),
                ]);

                if ($attempt >= self::MAX_ATTEMPTS) {
                    return null;
                }
                usleep(self::RETRY_SLEEP_MS * 1000);
            }
        }

        return null;
    }

    private function looksLikeNotFound(string $body): bool
    {
        $plain = mb_strtolower(strip_tags($body));
        foreach (['no fue encontrad', 'no se encontr', 'no existe', 'clave de rastreo no', 'sin resultados'] as $needle) {
            if (str_contains($plain, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function parseXml(string $body): ?\SimpleXMLElement
    {
        $trimmed = ltrim($body);
        if ($trimmed === '' || $trimmed[0] !== '<') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body);
            return $xml === false ? null : $xml;
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Busca el nodo que contiene los datos del beneficiario/liquidación.
     * El CEP suele venir como <SPEI_Tercero><Beneficiario .../></SPEI_Tercero>.
     */
    private function findLiquidationNode(\SimpleXMLElement $xml): ?\SimpleXMLElement
    {
        foreach (['Beneficiario', 'beneficiario'] as $tag) {
            $hit = $xml->xpath("//{$tag}");
            if (!empty($hit)) {
                return $hit[0];
            }
        }

        // Fallback: si la raíz misma trae atributos de monto/cuenta, úsala.
        if ($this->extractAttr($xml, ['MontoPago', 'Monto']) !== null
            || $this->extractAttr($xml, ['Cuenta', 'CuentaBeneficiario']) !== null) {
            return $xml;
        }

        return null;
    }

    private function extractEstado(\SimpleXMLElement $xml): ?string
    {
        foreach (['Estado', 'estado'] as $tag) {
            $hit = $xml->xpath("//{$tag}");
            if (!empty($hit)) {
                $val = trim((string) $hit[0]);
                if ($val !== '') {
                    return $val;
                }
            }
        }
        $attr = $this->extractAttr($xml, ['Estado', 'estado']);
        return $attr !== null ? trim($attr) : null;
    }

    /** Devuelve el primer atributo presente (case-insensitive por lista) de un nodo. */
    private function extractAttr(\SimpleXMLElement $node, array $candidates): ?string
    {
        $attrs = $node->attributes();
        if ($attrs === null) {
            return null;
        }
        foreach ($candidates as $name) {
            if (isset($attrs[$name])) {
                $val = trim((string) $attrs[$name]);
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return null;
    }
}
