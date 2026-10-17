<?php

namespace App\Services\Finance\Billing;

use App\Models\ClientFiscalData;
use App\Models\Invoice;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use App\Support\LogoPdf;
use App\Support\MontoEnLetras;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Variables de la plantilla «Prefactura.» (document_templates) para UNA factura de `invoices`
 * (item #480). Documento informativo: no es comprobante fiscal y nada aquí timbra.
 *
 * Dos mitades a propósito:
 *  - contexto(): lee la BD (cliente, datos fiscales, empresa, pago, referencia MEG, logo).
 *  - armar(): PURA — de ese contexto produce SIEMPRE las CLAVES completas como string. El
 *    validador de plantillas hace $data[clave] sobre cada variable registrada, así que una
 *    clave ausente o null sería un 500 al generar; la prueba unitaria cuida esa garantía.
 */
class PrefacturaDataProvider
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_PAGADO = 'pagado';

    /** Variables `${data.*}` propias de la Prefactura (grupo «Prefactura» del validador). */
    public const CLAVES = [
        'documento_titulo', 'documento_nota', 'chip_clase', 'chip_texto', 'banda_label', 'banda_nota',
        'receptor_rfc', 'receptor_nombre_fiscal', 'receptor_regimen_fiscal', 'receptor_cp_fiscal',
        'receptor_uso_cfdi', 'receptor_nota', 'conceptos_filas', 'subtotal', 'descuentos',
        'iva_monto', 'total', 'total_letra', 'bloque_pago', 'bloque_fiscal', 'leyenda_legal',
        'logo_pdf', 'aviso_privacidad',
    ];

    /** Variables `${data_dinamic.*}`: el validador no las toca, se reemplazan después. */
    public const DINAMICAS = [
        'data_dinamic.invoice_id', 'data_dinamic.invoice_date', 'data_dinamic.invoice_pay_up',
        'data_dinamic.invoice_period', 'data_dinamic.debit',
    ];

    /**
     * @return array{data: array<string,string>, dinamicas: array<string,string>}
     */
    public function paraFactura(Invoice $invoice, ?string $estado = null): array
    {
        return $this->armar($this->contexto($invoice, $estado));
    }

    public static function estadoDe(Invoice $invoice): string
    {
        return $invoice->status === Invoice::STATUS_PAID ? self::ESTADO_PAGADO : self::ESTADO_PENDIENTE;
    }

    /** Lo que la Prefactura necesita saber de la factura, ya resuelto desde la BD. */
    public function contexto(Invoice $invoice, ?string $estado = null): array
    {
        $invoice->loadMissing('items');
        $estado = in_array($estado, [self::ESTADO_PENDIENTE, self::ESTADO_PAGADO], true)
            ? $estado
            : self::estadoDe($invoice);

        $total = (float) $invoice->total;
        $saldo = (float) $invoice->pending_balance;
        $parcial = $estado === self::ESTADO_PENDIENTE
            && ($invoice->status === Invoice::STATUS_PARTIALLY_PAID || ($saldo > 0 && $saldo < $total));

        $empresa = CompanyInformation::first();
        $fiscal = ClientFiscalData::where('client_id', $invoice->client_id)->first();

        return [
            'numero'          => (string) ($invoice->number ?: $invoice->id),
            'estado'          => $estado,
            'parcial'         => $parcial,
            'saldo'           => $saldo,
            'subtotal'        => (float) $invoice->subtotal,
            'iva'             => (float) $invoice->tax,
            'total'           => $total,
            'vence'           => self::fecha($invoice->due_date),
            'periodo'         => self::periodoLegible($invoice->period),
            'conceptos'       => $invoice->items->map(fn ($i) => [
                'nombre'      => (string) $i->name,
                'descripcion' => (string) ($i->description ?? ''),
                'cantidad'    => (float) $i->quantity,
                'subtotal'    => (float) $i->subtotal,
                'iva'         => (float) $i->tax_amount,
                'total'       => (float) $i->total,
            ])->values()->all(),
            'fiscal'          => self::fiscalCompleto($fiscal),
            'correo_facturacion' => (string) (config('billing.correo_facturacion') ?: ($empresa->email ?? '')),
            'banco'           => (string) ($empresa->bank_name ?? ''),
            'cuenta'          => (string) ($empresa->bank_account ?? ''),
            'portal'          => (string) ($empresa->url_portal ?? ''),
            // FAC-03: si la columna aún no existe (antes de migrar) llega null → sin línea.
            'aviso_privacidad_url' => (string) ($empresa->url_aviso_privacidad ?? ''),
            // Persistir (no solo construir) la referencia: la conciliación solo reconoce las
            // que existen en client_payment_references (MegReferenceResolver). ensureFor() es
            // idempotente y la referencia es determinista por client_id.
            'referencia_pago' => $estado === self::ESTADO_PENDIENTE
                ? PaymentReferenceService::ensureFor((int) $invoice->client_id)->reference
                : '',
            'pago'            => $estado === self::ESTADO_PAGADO ? $this->datosDePago($invoice) : [],
            'logo'            => $this->logoDataUri(),
            'ahora'           => Carbon::now(),
        ];
    }

    /**
     * PURA: del contexto a las variables. Siempre devuelve todas las CLAVES y DINAMICAS como
     * string, aun con el contexto incompleto.
     *
     * @return array{data: array<string,string>, dinamicas: array<string,string>}
     */
    public function armar(array $c): array
    {
        $pagado = ($c['estado'] ?? null) === self::ESTADO_PAGADO;
        $parcial = ! $pagado && ! empty($c['parcial']);
        $total = (float) ($c['total'] ?? 0);
        $subtotal = (float) ($c['subtotal'] ?? 0);
        $iva = (float) ($c['iva'] ?? 0);
        $saldo = (float) ($c['saldo'] ?? 0);
        $montoBanda = $parcial ? $saldo : $total;
        $descuento = round($subtotal + $iva - $total, 2);
        $ahora = $c['ahora'] ?? Carbon::now();
        $numero = (string) ($c['numero'] ?? '');
        $vence = $c['vence'] ?? null;
        $fiscal = $c['fiscal'] ?? null;
        $pago = $c['pago'] ?? [];

        if ($pagado) {
            $notaBanda = 'Pagado el <b>' . e(self::dmy($pago['fecha'] ?? null)) . '</b>';
        } else {
            $notaBanda = $vence ? 'Fecha límite: <b>' . e(self::dmy($vence)) . '</b>' : 'Sin fecha límite';
            if ($parcial) {
                $notaBanda .= '<br/>Abonado <b>' . e(self::dinero(max(0, $total - $saldo))) . '</b> de '
                    . e(self::dinero($total));
            }
        }

        $data = [
            // Pagada = FACTURA; pendiente o parcial = PREFACTURA (item #505). Informativo en ambos.
            'documento_titulo'        => $pagado ? 'FACTURA' : 'PREFACTURA',
            'documento_nota'          => 'Sin valor fiscal',
            'chip_clase'              => $pagado ? 'chip-ok' : 'chip-pend',
            'chip_texto'              => $pagado ? 'PAGADA' : 'PENDIENTE DE PAGO',
            'banda_label'             => $pagado ? 'TOTAL PAGADO' : 'TOTAL A PAGAR',
            'banda_nota'              => $notaBanda,
            'receptor_rfc'            => e($fiscal['rfc'] ?? '—'),
            'receptor_nombre_fiscal'  => e($fiscal['razon_social'] ?? '—'),
            'receptor_regimen_fiscal' => e($fiscal['regimen'] ?? '—'),
            'receptor_cp_fiscal'      => e($fiscal['cp'] ?? '—'),
            'receptor_uso_cfdi'       => e($fiscal['uso'] ?? '—'),
            'receptor_nota'           => $fiscal ? '' : 'Sin datos fiscales registrados',
            'conceptos_filas'         => self::filasConceptos($c['conceptos'] ?? [], (string) ($c['periodo'] ?? '')),
            'subtotal'                => e(self::dinero($subtotal)),
            'descuentos'              => e($descuento > 0 ? '-' . self::dinero($descuento) : self::dinero(0)),
            'iva_monto'               => e(self::dinero($iva)),
            'total'                   => e(self::dinero($total)),
            'total_letra'             => e(MontoEnLetras::pesosMxn($total)),
            'bloque_pago'             => $pagado ? self::bloquePagado($pago) : self::bloquePendiente($c),
            'bloque_fiscal'           => self::bloqueFiscal($fiscal, (string) ($c['correo_facturacion'] ?? '')),
            'leyenda_legal'           => '<span class="strong">Documento informativo sin valor fiscal.</span> Emitido el '
                . e($ahora->format('d/m/Y H:i')) . ' &middot; Folio ' . e($numero) . '.',
            'logo_pdf'                => (string) (($c['logo'] ?? '') ?: LogoPdf::PIXEL_TRANSPARENTE),
            'aviso_privacidad'        => self::lineaAvisoPrivacidad((string) ($c['aviso_privacidad_url'] ?? '')),
        ];

        $dinamicas = [
            'data_dinamic.invoice_id'     => e($numero),
            'data_dinamic.invoice_date'   => e($ahora->format('d/m/Y')),
            'data_dinamic.invoice_pay_up' => e($vence ? self::dmy($vence) : '—'),
            'data_dinamic.invoice_period' => e((string) (($c['periodo'] ?? '') ?: '—')),
            'data_dinamic.debit'          => e(number_format($montoBanda, 2, '.', ',')),
        ];

        return ['data' => $data, 'dinamicas' => $dinamicas];
    }

    // ─── Bloques HTML ─────────────────────────────────────────────────────────────

    /**
     * FAC-03: URL del aviso de privacidad válida (http/https, sin espacios ni comillas, ≤500) o
     * null. La valida también el formulario de la empresa; aquí se repite porque el PDF no debe
     * confiar en lo que haya en la BD.
     */
    public static function urlAvisoPrivacidad(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\s"\'<>]/', $url)) {
            return null;
        }
        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($esquema, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    /** Línea del pie con el enlace al aviso; vacía si no hay URL válida (la línea no aparece). */
    private static function lineaAvisoPrivacidad(string $url): string
    {
        $url = self::urlAvisoPrivacidad($url);
        if ($url === null) {
            return '';
        }

        return '<div style="margin-top:2px;">Aviso de privacidad: <a href="' . e($url) . '" style="color:inherit;">'
            . e($url) . '</a></div>';
    }

    private static function filasConceptos(array $conceptos, string $periodo): string
    {
        if (! $conceptos) {
            return '<tr><td colspan="7" class="c mut">Sin conceptos registrados en esta factura.</td></tr>';
        }

        $filas = '';
        foreach (array_values($conceptos) as $n => $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $unitario = $cantidad > 0 ? $subtotal / $cantidad : $subtotal;
            $descripcion = trim((string) ($item['descripcion'] ?? ''));

            $filas .= '<tr' . ($n % 2 ? ' class="alt"' : '') . '>'
                . '<td class="c">' . ($n + 1) . '</td>'
                . '<td><span class="desc">' . e((string) ($item['nombre'] ?? '')) . '</span>'
                . ($descripcion !== '' ? '<br/><span class="sub">' . e($descripcion) . '</span>' : '') . '</td>'
                . '<td>' . e($periodo ?: '—') . '</td>'
                . '<td class="c">' . e(self::cantidad($cantidad)) . '</td>'
                . '<td class="r">' . e(self::dinero($unitario)) . '</td>'
                . '<td class="r">' . e(self::dinero((float) ($item['iva'] ?? 0))) . '</td>'
                . '<td class="r">' . e(self::dinero((float) ($item['total'] ?? 0))) . '</td>'
                . "</tr>\n";
        }

        return $filas;
    }

    private static function bloquePendiente(array $c): string
    {
        $cuenta = preg_replace('/\s+/', '', (string) ($c['cuenta'] ?? ''));
        $filas = self::filaKv('Banco', (string) ($c['banco'] ?? ''))
            // La etiqueta «CLABE» solo si de verdad tiene la forma de una (18 dígitos).
            . self::filaKv(preg_match('/^\d{18}$/', $cuenta) ? 'CLABE' : 'Cuenta', $cuenta);

        $referencia = (string) ($c['referencia_pago'] ?? '');
        if ($referencia !== '') {
            $filas .= '<tr><td class="k">Referencia</td><td class="v"><span class="ref">' . e($referencia) . '</span></td></tr>';
        }
        $portal = (string) ($c['portal'] ?? '');
        if ($portal !== '') {
            $filas .= self::filaKv('Pago en línea', $portal);
        }

        $nota = $referencia !== ''
            ? 'Escribe la referencia en el concepto de tu transferencia para identificar tu pago más rápido.'
            : 'Indica tu número de cliente en el concepto de tu transferencia.';

        return '<table class="box pay"><tr><td class="hd">C&oacute;mo pagar</td></tr><tr><td class="bd">'
            . '<table class="mt">' . $filas . '</table>'
            . '<div class="xs mut" style="margin-top:4px;">' . e($nota) . '</div>'
            . '</td></tr></table>';
    }

    private static function bloquePagado(array $pago): string
    {
        $filas = self::filaKv('Fecha de pago', self::dmy($pago['fecha'] ?? null))
            . self::filaKv('Método', (string) ($pago['metodo'] ?? ''))
            . self::filaKv('Referencia', (string) ($pago['referencia'] ?? ''));

        return '<table class="box pay"><tr><td class="hd">Pago recibido</td></tr><tr><td class="bd">'
            . '<table class="mt">' . $filas . '</table>'
            . '</td></tr></table>';
    }

    private static function bloqueFiscal(?array $fiscal, string $correo): string
    {
        $contacto = $correo !== '' ? ' a ' . e($correo) : ' con nuestra área de facturación';

        if ($fiscal) {
            return '<table class="fisc-ok"><tr><td class="hd2">Datos fiscales registrados</td></tr>'
                . '<tr><td class="bd2"><div class="note">Este documento es informativo y no sustituye a una '
                . 'factura fiscal. Con los datos fiscales registrados (RFC ' . e($fiscal['rfc']) . ') puedes '
                . 'solicitar la factura fiscal de este periodo' . $contacto . '.</div></td></tr></table>';
        }

        return '<table class="fisc-pend"><tr><td class="hd2">Sin datos fiscales</td></tr>'
            . '<tr><td class="bd2"><div class="note">No tenemos datos fiscales registrados para este cliente. '
            . 'Si necesitas factura fiscal, comp&aacute;rtenos tu RFC, raz&oacute;n social, r&eacute;gimen fiscal y '
            . 'c&oacute;digo postal (constancia de situaci&oacute;n fiscal)' . $contacto . '.</div></td></tr></table>';
    }

    private static function filaKv(string $etiqueta, string $valor): string
    {
        $valor = trim($valor) === '' ? '—' : $valor;

        return '<tr><td class="k">' . e($etiqueta) . '</td><td class="v">' . e($valor) . '</td></tr>';
    }

    // ─── Lectura de BD ────────────────────────────────────────────────────────────

    /** Datos fiscales SOLO si el cliente los activó y están completos; si no, null. */
    private static function fiscalCompleto(?ClientFiscalData $f): ?array
    {
        if (! $f || ! $f->facturacion_fiscal) {
            return null;
        }
        foreach (['rfc', 'razon_social', 'regimen_fiscal', 'codigo_postal_fiscal'] as $campo) {
            if (trim((string) $f->{$campo}) === '') {
                return null;
            }
        }

        $regimen = (string) $f->regimen_fiscal;
        $uso = (string) ($f->uso_cfdi ?? '');

        return [
            'rfc'          => strtoupper((string) $f->rfc),
            'razon_social' => (string) $f->razon_social,
            'regimen'      => trim($regimen . ' — ' . ($f->regimen_fiscal_nombre ?? ''), ' —'),
            'cp'           => (string) $f->codigo_postal_fiscal,
            'uso'          => $uso === '' ? '' : trim($uso . ' — ' . ($f->uso_cfdi_nombre ?? ''), ' —'),
        ];
    }

    private function datosDePago(Invoice $invoice): array
    {
        $pago = $invoice->payment_id ? $invoice->payment()->first() : null;

        $metodoId = $invoice->payment_method ?: $pago?->payment_method_id;
        $metodo = $metodoId ? DB::table('method_of_payments')->where('id', $metodoId)->value('type') : null;

        $referencia = $pago?->receipt ?: ($pago ? 'Pago #' . $pago->id : '');

        return [
            'fecha'      => self::fecha($invoice->payment_date) ?? self::fecha($pago?->date),
            'metodo'     => (string) ($metodo ?? ''),
            'referencia' => (string) $referencia,
        ];
    }

    /** Logo reducido como data URI (ver App\Support\LogoPdf). */
    public function logoDataUri(): string
    {
        return LogoPdf::dataUri();
    }

    // ─── Formatos ─────────────────────────────────────────────────────────────────

    public static function dinero(float $monto): string
    {
        return ($monto < 0 ? '-' : '') . '$' . number_format(abs($monto), 2, '.', ',');
    }

    private static function cantidad(float $cantidad): string
    {
        return fmod($cantidad, 1.0) == 0.0 ? (string) (int) $cantidad : number_format($cantidad, 2, '.', ',');
    }

    private static function dmy(?Carbon $fecha): string
    {
        return $fecha ? $fecha->format('d/m/Y') : '—';
    }

    /** Acepta los formatos que conviven en la BD: Y-m-d, Y-m-d H:i(:s) y el legacy d/m/Y. */
    public static function fecha($valor): ?Carbon
    {
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor);
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y'] as $formato) {
            try {
                $f = Carbon::createFromFormat($formato, $valor);
                if ($f !== false && $f->format($formato) === $valor) {
                    return $f;
                }
            } catch (\Throwable) {
                // probar el siguiente formato
            }
        }

        return null;
    }

    /**
     * «2026-11» → «noviembre-diciembre 2026» (el ciclo cruza de mes, igual que el asunto de la
     * prefactura automática). Diciembre cruza de año: «diciembre 2026-enero 2027».
     */
    public static function periodoLegible(?string $periodo): string
    {
        if (! $periodo || ! preg_match('/^(\d{4})-(\d{2})$/', trim($periodo), $m)) {
            return '';
        }
        $inicio = Carbon::create((int) $m[1], (int) $m[2], 1)->locale('es');
        $fin = $inicio->copy()->addMonthNoOverflow();

        return $inicio->year === $fin->year
            ? $inicio->monthName . '-' . $fin->monthName . ' ' . $inicio->year
            : $inicio->monthName . ' ' . $inicio->year . '-' . $fin->monthName . ' ' . $fin->year;
    }
}
