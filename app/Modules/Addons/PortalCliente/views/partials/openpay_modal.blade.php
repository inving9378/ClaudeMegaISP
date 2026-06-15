{{--
 * Modal de pago con tarjeta vía OpenPay.
 *
 * SEGURIDAD:
 * - Solo se exponen config('openpay.id') y config('openpay.public_key') al navegador.
 * - La private_key JAMÁS aparece aquí ni en ningún Blade/JS versionado.
 * - El PAN (número de tarjeta) se tokeniza en el navegador con openpay.js.
 *   Al backend solo viaja el TOKEN + device_session_id + invoice_id.
 *
 * Uso: @include('addon-portal-cliente::partials.openpay_modal', ['factura' => $factura])
--}}

{{-- ── Estilos del modal ─────────────────────────────────────────────── --}}
<style>
.openpay-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 1050;
    align-items: center; justify-content: center;
}
.openpay-overlay.activo { display: flex; }
.openpay-modal {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
    width: 100%; max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    position: relative;
}
.openpay-modal h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: .25rem; }
.openpay-importe {
    font-size: 2rem; font-weight: 800; color: var(--pcolor);
    margin: .75rem 0 1.25rem;
}
.op-group { margin-bottom: 1rem; }
.op-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .04em; }
.op-input {
    width: 100%; padding: .65rem .9rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--card);
    color: var(--text);
    font-size: .95rem;
    transition: border .15s;
}
.op-input:focus { outline: none; border-color: var(--pcolor); box-shadow: 0 0 0 3px rgba(0,87,168,.12); }
.op-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .5rem; }
.op-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
select.op-input { cursor: pointer; }

/* Estados del pago */
#op-estado { display: none; border-radius: 10px; padding: 1.5rem; text-align: center; }
#op-estado.procesando { display: block; background: var(--surface); }
#op-estado.aprobado  { display: block; background: #d4edda; color: #155724; }
#op-estado.rechazado { display: block; background: #f8d7da; color: #721c24; }
html.dark #op-estado.aprobado  { background: #1a3a22; color: #6dd08a; }
html.dark #op-estado.rechazado { background: #3a1219; color: #f08090; }

.op-spinner {
    width: 36px; height: 36px;
    border: 4px solid var(--border);
    border-top-color: var(--pcolor);
    border-radius: 50%;
    animation: op-spin .8s linear infinite;
    margin: 0 auto 1rem;
}
@keyframes op-spin { to { transform: rotate(360deg); } }
.btn-cerrar-modal {
    position: absolute; top: 1rem; right: 1rem;
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); font-size: 1.3rem; line-height: 1;
}
.op-brand {
    display: flex; align-items: center; justify-content: center; gap: .5rem;
    margin-top: 1rem; font-size: .72rem; color: var(--text-muted);
}
.op-brand img { height: 18px; }
</style>

{{-- ── Overlay + Modal ───────────────────────────────────────────────── --}}
<div id="openpay-overlay" class="openpay-overlay" role="dialog" aria-modal="true" aria-labelledby="op-titulo">

    <div class="openpay-modal">
        <button class="btn-cerrar-modal" onclick="opClosePagoModal()" aria-label="Cerrar">✕</button>

        {{-- Header --}}
        <h3 id="op-titulo">💳 Pago con tarjeta</h3>
        <div style="font-size:.82rem; color:var(--text-muted)">
            Factura #<span id="op-factura-numero">{{ $factura->number }}</span>
        </div>
        <div class="openpay-importe">${{ number_format($factura->total, 2) }} MXN</div>

        {{-- ── Estado (oculto por defecto, se muestra con JS) ── --}}
        <div id="op-estado">
            <div id="op-procesando-inner">
                <div class="op-spinner"></div>
                <div style="font-weight:600">Procesando pago…</div>
                <div style="font-size:.82rem; margin-top:.5rem">No cierres esta ventana.</div>
            </div>
            <div id="op-aprobado-inner" style="display:none">
                <div style="font-size:2.5rem; margin-bottom:.5rem">✅</div>
                <div style="font-weight:700; font-size:1.1rem">¡Pago exitoso!</div>
                <div style="font-size:.85rem; margin-top:.5rem">Tu pago fue procesado correctamente.</div>
            </div>
            <div id="op-rechazado-inner" style="display:none">
                <div style="font-size:2rem; margin-bottom:.5rem">❌</div>
                <div style="font-weight:700">Pago rechazado</div>
                <div id="op-error-msg" style="font-size:.85rem; margin-top:.5rem"></div>
                <button onclick="opReintentarPago()" class="btn btn-primary btn-sm" style="margin-top:1rem">Reintentar</button>
            </div>
        </div>

        {{-- ── Formulario (se oculta mientras procesa) ── --}}
        <form id="form-openpay" autocomplete="off" novalidate>
            @csrf
            {{-- campo oculto que llenará el JS --}}
            <input type="hidden" id="op-token"      name="token">
            <input type="hidden" id="op-session-id" name="device_session_id">
            <input type="hidden" id="op-invoice-id" name="invoice_id" value="{{ $factura->id }}">

            <div class="op-group">
                <label>Nombre del titular</label>
                <input type="text" class="op-input" id="op-holder" data-openpay-card="holder_name"
                    placeholder="Igual que en la tarjeta" autocomplete="cc-name" maxlength="50">
            </div>

            <div class="op-group">
                <label>Número de tarjeta</label>
                <input type="tel" class="op-input" id="op-number" data-openpay-card="card_number"
                    placeholder="0000 0000 0000 0000" autocomplete="cc-number" maxlength="19"
                    oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()">
            </div>

            <div class="op-row" style="margin-bottom:1rem">
                <div class="op-group" style="margin-bottom:0">
                    <label>Mes exp.</label>
                    <select class="op-input" data-openpay-card="expiration_month" autocomplete="cc-exp-month">
                        <option value="">MM</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="op-group" style="margin-bottom:0">
                    <label>Año exp.</label>
                    <select class="op-input" data-openpay-card="expiration_year" autocomplete="cc-exp-year">
                        <option value="">AA</option>
                        @for ($y = date('y'); $y <= date('y') + 10; $y++)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="op-group" style="margin-bottom:0">
                    <label>CVV</label>
                    <input type="tel" class="op-input" data-openpay-card="cvv2"
                        placeholder="CVV" autocomplete="cc-csc" maxlength="4"
                        oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
            </div>

            <button type="submit" id="op-btn-pagar" class="btn btn-primary" style="width:100%; padding:.75rem; font-size:1rem; font-weight:600">
                🔒 Pagar ${{ number_format($factura->total, 2) }} MXN
            </button>

            <div class="op-brand">
                <span>Pagos procesados por</span>
                <strong>OpenPay</strong>
                <span>· Sandbox activo</span>
            </div>
        </form>

    </div>
</div>

{{-- ── Scripts OpenPay ───────────────────────────────────────────────── --}}
@push('scripts')
{{-- openpay.js: tokenización de tarjeta en el navegador --}}
<script src="https://js.openpay.mx/openpay.v1.min.js"></script>
{{-- openpay-data.js: genera device_session_id para antifraude --}}
<script src="https://js.openpay.mx/openpay-data.v1.min.js"></script>

<script>
(function () {
    // ── Config pública (ID + public_key son seguros en el frontend) ────
    // La private_key NUNCA aparece aquí.
    OpenPay.setId('{{ config("openpay.id") }}');
    OpenPay.setApiKey('{{ config("openpay.public_key") }}');
    OpenPay.setSandboxMode({{ config('openpay.sandbox') ? 'true' : 'false' }});

    // Generar device_session_id para antifraude (lo llena en campo oculto)
    var deviceSessionId;
    try {
        deviceSessionId = OpenPay.deviceData.setup('form-openpay', 'op-session-id');
    } catch(e) {
        console.warn('[OpenPay] device_session_id no pudo generarse:', e.message);
        deviceSessionId = 'fallback-' + Date.now();
        document.getElementById('op-session-id').value = deviceSessionId;
    }

    // ── Abrir / cerrar modal ────────────────────────────────────────────
    window.opAbrirPagoModal = function () {
        document.getElementById('openpay-overlay').classList.add('activo');
        opReintentarPago(); // resetear estado
    };

    window.opClosePagoModal = function () {
        document.getElementById('openpay-overlay').classList.remove('activo');
        opReintentarPago();
    };

    // ── Resetear a formulario ──────────────────────────────────────────
    window.opReintentarPago = function () {
        document.getElementById('op-estado').style.display = 'none';
        document.getElementById('op-estado').className = '';
        document.getElementById('op-procesando-inner').style.display = 'block';
        document.getElementById('op-aprobado-inner').style.display  = 'none';
        document.getElementById('op-rechazado-inner').style.display = 'none';
        document.getElementById('form-openpay').style.display = 'block';
        document.getElementById('op-btn-pagar').disabled = false;
    };

    // ── Envío del formulario ────────────────────────────────────────────
    document.getElementById('form-openpay').addEventListener('submit', function (e) {
        e.preventDefault();

        // Mostrar estado procesando
        this.style.display = 'none';
        var est = document.getElementById('op-estado');
        est.style.display = 'block';
        est.className = 'procesando';
        document.getElementById('op-procesando-inner').style.display = 'block';
        document.getElementById('op-aprobado-inner').style.display  = 'none';
        document.getElementById('op-rechazado-inner').style.display = 'none';

        // Tokenizar en el navegador (el PAN nunca llega al servidor)
        OpenPay.token.extractFormAndCreate(
            'form-openpay',
            function (res) { opTokenSuccess(res.data.id); },
            function (res) { opTokenError(res.data); }
        );
    });

    function opTokenSuccess(token) {
        document.getElementById('op-token').value = token;

        var invoiceId = document.getElementById('op-invoice-id').value;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('/portal/facturas/' + invoiceId + '/pagar', {
            method : 'POST',
            headers: {
                'Content-Type'    : 'application/json',
                'Accept'          : 'application/json',
                'X-CSRF-TOKEN'    : csrfToken,
            },
            body: JSON.stringify({
                token            : token,
                device_session_id: deviceSessionId,
                invoice_id       : invoiceId,
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var est = document.getElementById('op-estado');
            if (data.success) {
                est.className = 'aprobado';
                document.getElementById('op-procesando-inner').style.display = 'none';
                document.getElementById('op-aprobado-inner').style.display  = 'block';
                // Recargar la página tras 2s para reflejar nuevo estado de la factura
                setTimeout(function () { window.location.reload(); }, 2200);
            } else {
                est.className = 'rechazado';
                document.getElementById('op-procesando-inner').style.display = 'none';
                document.getElementById('op-rechazado-inner').style.display = 'block';
                document.getElementById('op-error-msg').textContent = data.error || 'El pago fue rechazado.';
            }
        })
        .catch(function () {
            var est = document.getElementById('op-estado');
            est.className = 'rechazado';
            document.getElementById('op-procesando-inner').style.display = 'none';
            document.getElementById('op-rechazado-inner').style.display = 'block';
            document.getElementById('op-error-msg').textContent = 'Error de conexión. Por favor intenta de nuevo.';
        });
    }

    function opTokenError(errorData) {
        var est = document.getElementById('op-estado');
        est.className = 'rechazado';
        document.getElementById('op-procesando-inner').style.display = 'none';
        document.getElementById('op-rechazado-inner').style.display = 'block';

        var msg = 'Verifica los datos de tu tarjeta e intenta de nuevo.';
        if (errorData && errorData.error_code) {
            var msgs = {
                201: 'El CVV es inválido.',
                202: 'La tarjeta está vencida.',
                203: 'El número de tarjeta es incorrecto.',
                205: 'El nombre del titular es requerido.',
                206: 'El número de tarjeta es requerido.',
            };
            msg = msgs[errorData.error_code] || msg;
        }
        document.getElementById('op-error-msg').textContent = msg;
    }

    // Cerrar modal al hacer clic en el overlay
    document.getElementById('openpay-overlay').addEventListener('click', function (e) {
        if (e.target === this) opClosePagoModal();
    });
})();
</script>
@endpush
