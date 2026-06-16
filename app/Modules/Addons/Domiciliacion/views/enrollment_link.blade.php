<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago automático mensual — Meganet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 2rem 2.5rem;
            width: 100%;
            max-width: 440px;
        }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        .logo h1 { font-size: 1.4rem; color: #0057A8; font-weight: 700; }
        .logo p { font-size: .85rem; color: #666; margin-top: .25rem; }
        h2 { font-size: 1.1rem; color: #222; margin-bottom: 1.25rem; }
        label { display: block; font-size: .82rem; color: #555; margin-bottom: .3rem; font-weight: 600; }
        input[type="text"], input[type="tel"], select {
            width: 100%;
            padding: .6rem .85rem;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            font-size: .95rem;
            color: #222;
            background: #fafafa;
            margin-bottom: 1rem;
            transition: border-color .15s;
        }
        input:focus { outline: none; border-color: #0057A8; background: #fff; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .row .form-group { margin-bottom: 0; }
        .btn {
            display: block;
            width: 100%;
            padding: .75rem;
            background: #0057A8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: .5rem;
            transition: background .15s;
        }
        .btn:hover:not(:disabled) { background: #004080; }
        .btn:disabled { background: #9bb4d0; cursor: not-allowed; }
        .error-msg {
            background: #fff3f3;
            border-left: 4px solid #e53935;
            color: #b71c1c;
            padding: .75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: .9rem;
        }
        .success-box {
            text-align: center;
            padding: 1.5rem 0;
        }
        .success-box .check {
            font-size: 3rem;
            color: #2e7d32;
            display: block;
            margin-bottom: .75rem;
        }
        .success-box h2 { color: #2e7d32; font-size: 1.2rem; }
        .success-box p { color: #555; margin-top: .5rem; font-size: .9rem; }
        .secure { display: flex; align-items: center; gap: .4rem; color: #888; font-size: .78rem; margin-top: 1.25rem; }
        .secure svg { flex-shrink: 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>Meganet</h1>
        <p>Domiciliación a tarjeta</p>
    </div>

    @if($success)
        <div class="success-box">
            <span class="check">&#10003;</span>
            <h2>¡Tarjeta registrada!</h2>
            <p>Tu pago automático mensual quedó activado.<br>El cargo se realizará cada mes al vencimiento de tu factura.</p>
        </div>
    @else
        <h2>Registra tu tarjeta de crédito o débito</h2>

        @if($error)
            <div class="error-msg" id="js-error">{{ $error }}</div>
        @else
            <div class="error-msg" id="js-error" style="display:none;"></div>
        @endif

        <form id="enrollment-form" method="POST" action="{{ route('domiciliacion.public.store', ['token' => $link->token]) }}" novalidate>
            @csrf
            {{-- Token oculto que rellena openpay.js --}}
            <input type="hidden" name="token" id="openpay-token-hidden">

            <div class="form-group">
                <label>Número de tarjeta</label>
                <input type="tel" id="card-number" data-openpay-card="card_number"
                       placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
            </div>

            <div class="form-group">
                <label>Titular de la tarjeta</label>
                <input type="text" id="card-holder" data-openpay-card="holder_name"
                       placeholder="Como aparece en la tarjeta" autocomplete="cc-name">
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Vencimiento</label>
                    <input type="tel" id="card-exp-month" data-openpay-card="expiration_month"
                           placeholder="MM" maxlength="2" autocomplete="cc-exp-month">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <input type="tel" id="card-exp-year" data-openpay-card="expiration_year"
                           placeholder="YY" maxlength="2" autocomplete="cc-exp-year">
                </div>
            </div>

            <div class="form-group">
                <label>CVV</label>
                <input type="tel" id="card-cvv" data-openpay-card="cvv2"
                       placeholder="123" maxlength="4" autocomplete="cc-csc">
            </div>

            <button type="submit" class="btn" id="submit-btn">Registrar tarjeta</button>
        </form>

        <div class="secure">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Tus datos se cifran con TLS. El número de tarjeta nunca llega a nuestros servidores.
        </div>
    @endif
</div>

@if(!$success)
{{-- OpenPay SDK — tokeniza en el navegador; PAN nunca sale del dispositivo --}}
<script src="https://js.openpay.mx/openpay.v1.min.js"></script>
<script>
    OpenPay.setId({{ json_encode($openpayId) }});
    OpenPay.setApiKey({{ json_encode($openpayKey) }});
    OpenPay.setSandboxMode({{ $sandbox ? 'true' : 'false' }});

    const form      = document.getElementById('enrollment-form');
    const errorDiv  = document.getElementById('js-error');
    const submitBtn = document.getElementById('submit-btn');

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Registrar tarjeta';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorDiv.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Procesando…';

        OpenPay.token.create(form, function (response) {
            document.getElementById('openpay-token-hidden').value = response.data.id;
            // Limpiar los datos del PAN antes de enviar el form
            ['card-number', 'card-cvv'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            form.submit();
        }, function (response) {
            const msgs = {
                2001: 'La tarjeta ya expiró.',
                2004: 'El código CVV es incorrecto.',
                2005: 'La fecha de vencimiento es incorrecta.',
                2006: 'Código de seguridad inválido.',
                3001: 'La tarjeta fue rechazada.',
                3002: 'La tarjeta ha caducado.',
                3004: 'Fondos insuficientes.',
            };
            const code = response.data?.error_code;
            showError(msgs[code] || 'Datos de tarjeta inválidos. Verifica e intenta de nuevo.');
        });
    });

    // Formato visual del número de tarjeta
    document.getElementById('card-number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
    });
</script>
@endif
</body>
</html>
