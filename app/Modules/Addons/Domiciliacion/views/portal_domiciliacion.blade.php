@extends('addon-portal-cliente::layouts.portal')
@section('title', 'Pago Automático Mensual')

@section('content')
<div class="page-header">
    <h1>💳 Pago Automático Mensual</h1>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div style="background:#f0fff4; border-left:4px solid #28a745; color:#155724; padding:.85rem 1.25rem; border-radius:8px; margin-bottom:1.25rem; font-size:.9rem;">
    ✅ {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:#fff3f3; border-left:4px solid #dc3545; color:#721c24; padding:.85rem 1.25rem; border-radius:8px; margin-bottom:1.25rem; font-size:.9rem;">
    ❌ {{ session('error') }}
</div>
@endif
<div id="js-error" style="background:#fff3f3; border-left:4px solid #dc3545; color:#721c24; padding:.85rem 1.25rem; border-radius:8px; margin-bottom:1.25rem; font-size:.9rem; display:none;"></div>

@if($card)
{{-- ── Tarjeta activa ────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-title">✅ Domiciliación activa</div>
    <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap; padding:.5rem 0">
        <div style="font-size:2rem;">
            @switch(strtolower($card->card_brand ?? ''))
                @case('visa') 💳 @break
                @case('mastercard') 💳 @break
                @default 💳
            @endswitch
        </div>
        <div>
            <div style="font-size:1.2rem; font-weight:700; letter-spacing:.1em; font-family:monospace">
                •••• •••• •••• {{ $card->card_last4 }}
            </div>
            <div style="color:var(--text-muted); font-size:.85rem; margin-top:.25rem;">
                {{ strtoupper($card->card_brand ?? 'Tarjeta') }} &nbsp;|&nbsp;
                Vence {{ $card->card_exp }} &nbsp;|&nbsp;
                {{ $card->cardholder }}
            </div>
            <div style="color:var(--text-muted); font-size:.8rem; margin-top:.2rem;">
                Registrada: {{ $card->consent_at?->format('d/m/Y H:i') ?? '—' }}
            </div>
        </div>
    </div>
    <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border);">
        <p style="font-size:.875rem; color:var(--text-muted); margin-bottom:.75rem;">
            El cargo se realiza automáticamente al vencimiento de tu factura mensual.
            Si deseas cancelar la domiciliación, haz clic en el botón de abajo.
        </p>
        <form method="POST" action="{{ route('portal.domiciliacion.cancelar', $card->id) }}"
              onsubmit="return confirm('¿Seguro que deseas cancelar el cobro automático?')">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:#dc3545; color:#fff; border:none; border-radius:6px; padding:.5rem 1.25rem; cursor:pointer; font-size:.875rem; font-weight:600;">
                Cancelar domiciliación
            </button>
        </form>
    </div>
</div>

@else
{{-- ── Sin tarjeta — formulario de enrolamiento ────────────────────────── --}}
<div class="card">
    <div class="card-title">Registrar tarjeta para cobro automático</div>
    <p style="color:var(--text-muted); font-size:.875rem; margin-bottom:1.5rem; line-height:1.6;">
        Registra tu tarjeta de crédito o débito para que tu mensualidad se cobre automáticamente
        cada mes. No necesitas hacer nada más: recibirás una notificación cuando se realice el cargo.
    </p>

    <form id="enroll-form" method="POST"
          action="{{ route('portal.domiciliacion.enrolar') }}" novalidate>
        @csrf
        <input type="hidden" name="token" id="openpay-token-hidden">

        <div style="margin-bottom:1rem;">
            <label style="display:block; font-size:.82rem; color:var(--text-muted); font-weight:600; margin-bottom:.35rem;">
                Número de tarjeta
            </label>
            <input type="tel" id="card-number" data-openpay-card="card_number"
                   placeholder="0000 0000 0000 0000" maxlength="19"
                   autocomplete="cc-number"
                   style="width:100%; padding:.6rem .9rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:.95rem;">
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block; font-size:.82rem; color:var(--text-muted); font-weight:600; margin-bottom:.35rem;">
                Titular de la tarjeta
            </label>
            <input type="text" id="card-holder" data-openpay-card="holder_name"
                   placeholder="Como aparece en la tarjeta"
                   autocomplete="cc-name"
                   style="width:100%; padding:.6rem .9rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:.95rem;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div>
                <label style="display:block; font-size:.82rem; color:var(--text-muted); font-weight:600; margin-bottom:.35rem;">Mes</label>
                <input type="tel" id="card-exp-month" data-openpay-card="expiration_month"
                       placeholder="MM" maxlength="2" autocomplete="cc-exp-month"
                       style="width:100%; padding:.6rem .9rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:.95rem;">
            </div>
            <div>
                <label style="display:block; font-size:.82rem; color:var(--text-muted); font-weight:600; margin-bottom:.35rem;">Año</label>
                <input type="tel" id="card-exp-year" data-openpay-card="expiration_year"
                       placeholder="YY" maxlength="2" autocomplete="cc-exp-year"
                       style="width:100%; padding:.6rem .9rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:.95rem;">
            </div>
            <div>
                <label style="display:block; font-size:.82rem; color:var(--text-muted); font-weight:600; margin-bottom:.35rem;">CVV</label>
                <input type="tel" id="card-cvv" data-openpay-card="cvv2"
                       placeholder="123" maxlength="4" autocomplete="cc-csc"
                       style="width:100%; padding:.6rem .9rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text); font-size:.95rem;">
            </div>
        </div>

        <button type="submit" id="enroll-btn"
                style="width:100%; padding:.75rem; background:var(--pcolor); color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;">
            Registrar tarjeta
        </button>
    </form>

    <div style="display:flex; align-items:center; gap:.5rem; color:var(--text-muted); font-size:.78rem; margin-top:1rem;">
        🔒 Tus datos se cifran con TLS. El número de tarjeta nunca llega a nuestros servidores.
    </div>
</div>
@endif

{{-- OpenPay JS — solo si no hay tarjeta registrada --}}
@if(!$card)
<script src="https://js.openpay.mx/openpay.v1.min.js"></script>
<script>
    OpenPay.setId({{ json_encode($openpayId) }});
    OpenPay.setApiKey({{ json_encode($openpayKey) }});
    OpenPay.setSandboxMode({{ $sandbox ? 'true' : 'false' }});

    const form      = document.getElementById('enroll-form');
    const errorDiv  = document.getElementById('js-error');
    const btn       = document.getElementById('enroll-btn');

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Registrar tarjeta';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorDiv.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Procesando…';

        OpenPay.token.create(form, function (response) {
            document.getElementById('openpay-token-hidden').value = response.data.id;
            document.getElementById('card-number').value = '';
            document.getElementById('card-cvv').value = '';
            form.submit();
        }, function (response) {
            const msgs = {
                2001: 'La tarjeta ya expiró.',
                2004: 'El código CVV es incorrecto.',
                2005: 'La fecha de vencimiento es incorrecta.',
                3001: 'La tarjeta fue rechazada.',
                3002: 'La tarjeta ha caducado.',
                3004: 'Fondos insuficientes.',
            };
            const code = response.data?.error_code;
            showError(msgs[code] || 'Datos de tarjeta inválidos. Verifica e intenta de nuevo. (Código: ' + code + ')');
        });
    });

    document.getElementById('card-number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
    });
</script>
@endif
@endsection
