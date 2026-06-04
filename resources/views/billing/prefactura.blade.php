<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prefactura {{ $invoice->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 28px 32px; }

        /* Encabezado */
        .header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header td { vertical-align: middle; }
        .logo img { height: 55px; }
        .company-info { text-align: right; font-size: 11px; color: #444; line-height: 1.5; }
        .company-info strong { font-size: 13px; color: #1a1a1a; }

        /* Banda de título */
        .title-band { background: #1a5faa; color: #fff; padding: 8px 14px; border-radius: 4px; margin-bottom: 16px; }
        .title-band h1 { font-size: 16px; font-weight: bold; letter-spacing: .5px; display: inline-block; }
        .title-band .no-fiscal { float: right; font-size: 9px; background: #e5b53a; color: #1a1a1a; padding: 3px 7px; border-radius: 3px; font-weight: bold; margin-top: 2px; }

        /* Meta (número, período, vencimiento) */
        .meta-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-box { border: 1px solid #d0d7e2; border-radius: 4px; padding: 8px 12px; vertical-align: top; }
        .meta-box .label { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: .5px; }
        .meta-box .value { font-size: 12px; font-weight: bold; color: #1a1a1a; margin-top: 2px; }

        /* Datos cliente */
        .section-title { font-size: 10px; text-transform: uppercase; color: #1a5faa; font-weight: bold; letter-spacing: .6px; border-bottom: 1px solid #d0d7e2; padding-bottom: 3px; margin-bottom: 7px; }
        .client-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .client-table td { padding: 3px 0; font-size: 11px; vertical-align: top; }
        .client-table .lbl { color: #666; width: 130px; }

        /* Conceptos */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table th { background: #1a5faa; color: #fff; font-size: 10px; padding: 6px 10px; text-align: left; }
        .items-table th.r { text-align: right; }
        .items-table td { padding: 6px 10px; font-size: 11px; border-bottom: 1px solid #eaeef3; }
        .items-table td.r { text-align: right; }
        .items-table tr:nth-child(even) td { background: #f6f9fd; }

        /* Totales */
        .totals { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .totals td { padding: 4px 10px; }
        .totals .tl { font-size: 11px; color: #555; text-align: right; }
        .totals .tv { font-size: 11px; font-weight: bold; text-align: right; width: 110px; }
        .totals .total-row td { background: #1a5faa; color: #fff; font-size: 14px; border-radius: 2px; }

        /* Instrucciones de pago */
        .payment-box { border: 1px solid #d0d7e2; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; }
        .payment-box .methods { width: 100%; border-collapse: collapse; }
        .payment-box .methods td { vertical-align: top; padding: 4px 8px; font-size: 10px; border-right: 1px solid #e0e5ed; }
        .payment-box .methods td:last-child { border-right: none; }
        .payment-box .methods .method-title { font-size: 10px; font-weight: bold; color: #1a5faa; margin-bottom: 2px; }

        /* Pie */
        .footer { font-size: 9px; color: #888; text-align: center; border-top: 1px solid #d0d7e2; padding-top: 8px; }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Encabezado ── --}}
    <table class="header">
        <tr>
            <td class="logo" style="width:50%">
                @if($company->url_logo)
                    <img src="{{ public_path(ltrim($company->url_logo, '/')) }}" alt="{{ $company->company_name }}">
                @else
                    <span style="font-size:18px;font-weight:bold;color:#1a5faa">{{ $company->company_name }}</span>
                @endif
            </td>
            <td class="company-info">
                <strong>{{ $company->company_name }}</strong><br>
                @if($company->company_street)
                    {{ $company->company_street }} {{ $company->company_external_number }}
                    @if($company->company_internal_number) Int. {{ $company->company_internal_number }} @endif
                    <br>
                @endif
                {{ $company->colony_name }}, {{ $company->municipality_name }},
                {{ $company->state_name }}<br>
                C.P. {{ $company->company_postal_code }}<br>
                RFC: {{ $company->rfc }}
            </td>
        </tr>
    </table>

    {{-- ── Banda título ── --}}
    <div class="title-band">
        <h1>PREFACTURA</h1>
        <span class="no-fiscal">SIN VALOR FISCAL</span>
    </div>

    {{-- ── Meta ── --}}
    <table class="meta-row">
        <tr>
            <td style="width:33%;padding-right:6px">
                <div class="meta-box">
                    <div class="label">Número de prefactura</div>
                    <div class="value">{{ $invoice->number }}</div>
                </div>
            </td>
            <td style="width:33%;padding:0 3px">
                <div class="meta-box">
                    <div class="label">Período</div>
                    <div class="value">{{ $periodLabel }}</div>
                </div>
            </td>
            <td style="width:33%;padding-left:6px">
                <div class="meta-box">
                    <div class="label">Fecha límite de pago</div>
                    <div class="value" style="color:#c0392b">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Datos del cliente ── --}}
    <div class="section-title">Datos del cliente</div>
    <table class="client-table">
        <tr>
            <td class="lbl">Nombre:</td>
            <td>{{ $client->client_main_information->full_name ?? $client->client_main_information->name }}</td>
            <td style="width:20px"></td>
            <td class="lbl">ID cliente:</td>
            <td>{{ $client->id }}</td>
        </tr>
        <tr>
            <td class="lbl">Dirección:</td>
            <td colspan="4">
                {{ $client->client_main_information->street ?? '' }}
                {{ $client->client_main_information->external_number ?? '' }}
                @if($client->client_main_information->internal_number ?? '')
                    Int. {{ $client->client_main_information->internal_number }}
                @endif,
                {{ $client->client_main_information->colony ?? '' }},
                {{ $client->client_main_information->municipality ?? '' }},
                {{ $client->client_main_information->state ?? '' }}
                C.P. {{ $client->client_main_information->zip ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Correo:</td>
            <td>{{ $client->client_main_information->email ?? '—' }}</td>
            <td></td>
            <td class="lbl">Teléfono:</td>
            <td>{{ $client->client_main_information->phone ?? '—' }}</td>
        </tr>
    </table>

    {{-- ── Conceptos ── --}}
    <div class="section-title">Conceptos a facturar</div>
    @php
        $subtotalCalc = 0;
        $taxCalc      = 0;
    @endphp
    <table class="items-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="r" style="width:80px">Subtotal</th>
                <th class="r" style="width:70px">IVA 16%</th>
                <th class="r" style="width:80px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                @php
                    $subtotalCalc += $item->subtotal;
                    $taxCalc      += $item->tax_amount;
                @endphp
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="r">${{ number_format($item->subtotal, 2) }}</td>
                    <td class="r">${{ number_format($item->tax_amount, 2) }}</td>
                    <td class="r">${{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Totales ── --}}
    <table class="totals">
        <tr>
            <td class="tl">Subtotal:</td>
            <td class="tv">${{ number_format($invoice->subtotal ?? $subtotalCalc, 2) }}</td>
        </tr>
        <tr>
            <td class="tl">IVA (16%):</td>
            <td class="tv">${{ number_format($invoice->tax ?? $taxCalc, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td class="tl" style="color:#fff;font-weight:bold">TOTAL A PAGAR:</td>
            <td class="tv" style="color:#fff">${{ number_format($invoice->total, 2) }} MXN</td>
        </tr>
    </table>

    {{-- ── Instrucciones de pago ── --}}
    <div class="payment-box">
        <div class="section-title" style="margin-bottom:8px">Instrucciones de pago</div>
        <table class="methods">
            <tr>
                <td>
                    <div class="method-title">&#127970; Pago en mostrador</div>
                    {{ $company->company_street ?? '' }} {{ $company->company_external_number ?? '' }},
                    {{ $company->colony_name }}, {{ $company->municipality_name }}<br>
                    Lun–Sáb 9:00–18:00 · Tel: {{ $company->atention_client_phone ?? '—' }}
                </td>
                <td>
                    <div class="method-title">&#127968; SPEI / Transferencia</div>
                    Banco: {{ $company->bank_name ?? '—' }}<br>
                    Cuenta: {{ $company->bank_account ?? '—' }}<br>
                    Beneficiario: {{ $company->company_name }}
                </td>
                <td>
                    <div class="method-title">&#127981; OXXO Pay</div>
                    Referencia: ID cliente <strong>{{ $client->id }}</strong><br>
                    Disponible en tiendas OXXO<br>
                    Indicar ID cliente al pagar
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Pie ── --}}
    <div class="footer">
        Este documento es únicamente un aviso de cobro y <strong>NO tiene valor fiscal</strong>.
        No sustituye a una factura CFDI. Generado el {{ now()->format('d/m/Y H:i') }}.
    </div>

</div>
</body>
</html>
