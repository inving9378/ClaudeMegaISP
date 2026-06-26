<!DOCTYPE html>
<html lang="es" data-spa-skip>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pagar mi servicio — Meganet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; color: #222; padding: 2rem 1rem; }
        .wrap { max-width: 480px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 1.75rem 1.75rem; margin-bottom: 1.25rem; }
        .logo { text-align: center; margin-bottom: 1.25rem; }
        .logo img { max-height: 48px; }
        .logo p { font-size: .8rem; color: #777; margin-top: .4rem; }
        h2 { font-size: 1.1rem; margin-bottom: .25rem; }
        .sub { font-size: .85rem; color: #666; margin-bottom: 1.25rem; }
        .monto { text-align: center; font-size: 2rem; font-weight: 700; color: #0057A8; margin: .5rem 0 1rem; }
        .row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .65rem 0; border-bottom: 1px solid #eef0f3; }
        .row:last-child { border-bottom: 0; }
        .row .k { font-size: .8rem; color: #777; }
        .row .v { font-size: .92rem; font-weight: 600; text-align: right; word-break: break-all; }
        .copy-row { background: #f7f9fc; border: 1px dashed #c9d4e3; border-radius: 8px; padding: .75rem .9rem; margin: .6rem 0; }
        .copy-row .k { font-size: .72rem; color: #888; text-transform: uppercase; letter-spacing: .04em; }
        .copy-flex { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .25rem; }
        .copy-flex .val { font-size: 1.05rem; font-weight: 700; letter-spacing: .02em; }
        button.copy { border: 0; background: #0057A8; color: #fff; font-size: .78rem; font-weight: 600; padding: .4rem .7rem; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        button.copy:active { transform: scale(.97); }
        .badge { display: inline-block; font-size: .75rem; padding: .25rem .6rem; border-radius: 999px; background: #fff4e5; color: #b25e00; font-weight: 600; }
        .steps { display: grid; gap: .6rem; }
        .step { display: flex; gap: .75rem; align-items: flex-start; background: #f7f9fc; border-radius: 8px; padding: .7rem .85rem; }
        .step .n { flex: 0 0 26px; height: 26px; background: #0057A8; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; }
        .step .t { font-size: .85rem; color: #444; }
        label { display: block; font-size: .8rem; color: #555; margin: .75rem 0 .3rem; font-weight: 600; }
        input, select { width: 100%; padding: .6rem .8rem; border: 1px solid #d0d5dd; border-radius: 6px; font-size: .95rem; background: #fafafa; }
        .hint { font-size: .72rem; color: #999; margin-top: .25rem; }
        .submit { width: 100%; margin-top: 1.25rem; border: 0; background: #00a34a; color: #fff; font-size: 1rem; font-weight: 700; padding: .85rem; border-radius: 8px; cursor: pointer; }
        .submit:active { transform: scale(.99); }
        .errs { background: #fdecec; border: 1px solid #f5c2c2; color: #b42318; border-radius: 8px; padding: .7rem .9rem; font-size: .85rem; margin-bottom: 1rem; }
        .errs ul { margin: .25rem 0 0 1.1rem; }
        .ok-flash { background: #e9f9ef; border: 1px solid #b6e6c8; color: #07703a; border-radius: 8px; padding: .7rem .9rem; font-size: .85rem; margin-bottom: 1rem; }
        .foot { text-align: center; font-size: .75rem; color: #aaa; margin-top: .5rem; }
        .toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #222; color: #fff; padding: .6rem 1rem; border-radius: 8px; font-size: .85rem; opacity: 0; transition: opacity .2s; pointer-events: none; }
        .toast.show { opacity: .95; }
    </style>
</head>
<body data-spa-skip>
<div class="wrap">

    <div class="logo">
        <img src="{{ asset('images/logo_meganet_oficial.png') }}" alt="Meganet">
        <p>Meganet Telecomunicaciones</p>
    </div>

    <div class="card">
        <h2>Hola, {{ $clienteNombre }}</h2>
        <p class="sub">{{ $concepto }}</p>

        <div class="monto">{{ $montoFmt }}</div>

        @if($diasRestantes !== null)
            <div style="text-align:center; margin-bottom:1rem;">
                <span class="badge">Esta liga vence en {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día' : 'días' }}</span>
            </div>
        @endif

        <div class="copy-row">
            <div class="k">CLABE para tu transferencia</div>
            <div class="copy-flex">
                <span class="val" id="clabe">{{ $account->clabe }}</span>
                <button class="copy" type="button" data-copy="clabe">Copiar CLABE</button>
            </div>
        </div>

        <div class="copy-row">
            <div class="k">Monto exacto</div>
            <div class="copy-flex">
                <span class="val" id="monto">{{ number_format((float) $link->monto_esperado, 2, '.', '') }}</span>
                <button class="copy" type="button" data-copy="monto">Copiar monto</button>
            </div>
        </div>

        <div class="row"><span class="k">Banco</span><span class="v">{{ $account->banco ?: '—' }}</span></div>
        <div class="row"><span class="k">Beneficiario</span><span class="v">{{ $account->beneficiario ?: $account->titular ?: 'Meganet' }}</span></div>
        <div class="row"><span class="k">Concepto / Referencia</span><span class="v">{{ $link->referencia_unica }}</span></div>
    </div>

    <div class="card">
        <h2>¿Cómo transferir desde tu app bancaria?</h2>
        <div class="sub">Tres pasos sencillos</div>
        <div class="steps">
            <div class="step"><div class="n">1</div><div class="t">Abre tu app, ve a <b>Transferir / SPEI</b> y agrega la <b>CLABE</b> de arriba como destino.</div></div>
            <div class="step"><div class="n">2</div><div class="t">Captura el <b>monto exacto</b> y en el concepto escribe tu <b>referencia</b> ({{ $link->referencia_unica }}).</div></div>
            <div class="step"><div class="n">3</div><div class="t">Confirma la transferencia y <b>copia tu clave de rastreo</b>. La necesitas para reportar tu pago abajo.</div></div>
        </div>
    </div>

    <div class="card">
        <h2>Ya transferí — reportar mi pago</h2>
        <div class="sub">Captura los datos de tu transferencia para validarla.</div>

        @if(session('ok'))
            <div class="ok-flash">{{ session('ok') }}</div>
        @endif

        @if($errors->any())
            <div class="errs">
                Revisa lo siguiente:
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="/f/{{ $link->token }}/reportar" method="POST" enctype="multipart/form-data">
            @csrf

            <label for="clave_rastreo">Clave de rastreo *</label>
            <input type="text" id="clave_rastreo" name="clave_rastreo" value="{{ old('clave_rastreo') }}" maxlength="18" minlength="18" required placeholder="18 caracteres (la da tu banco)">
            <div class="hint">Son 18 caracteres alfanuméricos. Aparece en tu comprobante SPEI.</div>

            <label for="banco_emisor">Banco desde el que transferiste *</label>
            <select id="banco_emisor" name="banco_emisor" required>
                <option value="">Selecciona tu banco…</option>
                @foreach(\App\Modules\Addons\PortalPago\Support\BancosCatalogo::comunes() as $b)
                    <option value="{{ $b }}" @selected(old('banco_emisor') === $b)>{{ $b }}</option>
                @endforeach
            </select>

            <label for="fecha_operacion">Fecha de la transferencia *</label>
            <input type="date" id="fecha_operacion" name="fecha_operacion" value="{{ old('fecha_operacion') }}" max="{{ now()->format('Y-m-d') }}" required>

            <label for="monto_reportado">Monto que transferiste *</label>
            <input type="number" step="0.01" min="0" id="monto_reportado" name="monto_reportado" value="{{ old('monto_reportado', number_format((float) $link->monto_esperado, 2, '.', '')) }}" required>

            <label for="comprobante">Comprobante (opcional)</label>
            <input type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf">
            <div class="hint">Imagen o PDF, máximo 5 MB.</div>

            <button class="submit" type="submit">Reportar mi pago</button>
        </form>
    </div>

    <div class="foot">Meganet Telecomunicaciones · Pago seguro por transferencia SPEI</div>
</div>

<div class="toast" id="toast"></div>

<script>
    (function () {
        function toast(msg) {
            var t = document.getElementById('toast');
            t.textContent = msg; t.classList.add('show');
            setTimeout(function () { t.classList.remove('show'); }, 1600);
        }
        function copy(text, label) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { toast(label + ' copiado'); })
                    .catch(function () { fallback(text, label); });
            } else { fallback(text, label); }
        }
        function fallback(text, label) {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); toast(label + ' copiado'); } catch (e) {}
            document.body.removeChild(ta);
        }
        document.querySelectorAll('button.copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var el = document.getElementById(btn.getAttribute('data-copy'));
                if (!el) return;
                copy(el.textContent.trim(), btn.getAttribute('data-copy') === 'clabe' ? 'CLABE' : 'Monto');
            });
        });
    })();
</script>
</body>
</html>
