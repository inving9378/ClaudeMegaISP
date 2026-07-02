<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Prueba de extracción de comprobante (IA)</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 820px; margin: 0 auto; padding: 24px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
        label { display: block; font-size: 13px; margin: 0 0 6px; color: #cbd5e1; }
        select, input[type=file] { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #e2e8f0; margin-bottom: 14px; }
        button { background: #06b6d4; color: #04222b; font-weight: 600; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-size: 14px; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .warn { background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: 12px; padding: 8px 10px; border-radius: 8px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; font-size: 14px; vertical-align: top; }
        th { color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .val-null { color: #f87171; font-style: italic; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .conf-alta { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .conf-media { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
        .conf-baja { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
        .err { background: #450a0a; border: 1px solid #991b1b; color: #fecaca; padding: 12px; border-radius: 8px; }
        .muted { color: #64748b; font-size: 12px; }
        pre { background: #0b1220; border: 1px solid #334155; border-radius: 8px; padding: 12px; overflow: auto; font-size: 12px; color: #cbd5e1; white-space: pre-wrap; }
        details { margin-top: 12px; }
        summary { cursor: pointer; color: #94a3b8; font-size: 13px; }
        .crit { color: #f59e0b; font-size: 11px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Prueba de extracción de comprobante (IA)</h1>
    <p class="sub">Sube una imagen de comprobante y mira qué extrae la IA. <b>Aislado</b>: no aplica pagos, no busca cliente, no envía nada.</p>

    <div class="card">
        <div class="warn">Herramienta de prueba (super-administrator / DESARROLLADOR). Solo lectura por IA — no se guarda ni aplica ningún pago.</div>

        <label for="tipo">Tipo de comprobante</label>
        <select id="tipo">
            @foreach ($types as $t)
                <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
        </select>

        <label for="imagen">Imagen del comprobante (JPG/PNG/WebP, máx. 10 MB)</label>
        <input type="file" id="imagen" accept="image/jpeg,image/png,image/webp">

        <button id="btn" type="button">Extraer con IA</button>
    </div>

    <div id="resultado"></div>
</div>

<script>
    const PROCESAR_URL = @json(route('finanzas.extraccion-comprobante.procesar'));
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const CRITICOS = ['clave_rastreo', 'monto'];

    const btn = document.getElementById('btn');
    const out = document.getElementById('resultado');

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    btn.addEventListener('click', async () => {
        const file = document.getElementById('imagen').files[0];
        const tipo = document.getElementById('tipo').value;
        if (!file) { out.innerHTML = '<div class="card err">Selecciona una imagen primero.</div>'; return; }

        btn.disabled = true;
        out.innerHTML = '<div class="card muted">Procesando con la IA… (puede tardar unos segundos)</div>';

        const fd = new FormData();
        fd.append('imagen', file);
        fd.append('tipo', tipo);

        try {
            const resp = await fetch(PROCESAR_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await resp.json();
            if (!resp.ok) {
                const msg = data.message || ('Error HTTP ' + resp.status);
                out.innerHTML = '<div class="card err">' + esc(msg) + '</div>';
                return;
            }
            render(data);
        } catch (e) {
            out.innerHTML = '<div class="card err">Error de red: ' + esc(e.message) + '</div>';
        } finally {
            btn.disabled = false;
        }
    });

    function render(data) {
        let html = '';

        if (!data.ok) {
            html += '<div class="card err"><b>No se pudo extraer.</b><br>' + esc(data.error) + '</div>';
        } else {
            html += '<div class="card"><h1 style="font-size:16px">Campos extraídos <span class="muted">(' + esc(data.document_type) + ')</span></h1>';
            html += '<table><thead><tr><th>Campo</th><th>Valor</th><th>Confianza</th></tr></thead><tbody>';
            for (const [campo, info] of Object.entries(data.fields || {})) {
                const critico = CRITICOS.includes(campo) ? ' <span class="crit">★ crítico</span>' : '';
                const valor = (info.value === null || info.value === undefined)
                    ? '<span class="val-null">no legible</span>'
                    : esc(info.value);
                const conf = esc(info.confidence);
                html += '<tr><td>' + esc(campo) + critico + '</td><td>' + valor +
                        '</td><td><span class="badge conf-' + conf + '">' + conf + '</span></td></tr>';
            }
            html += '</tbody></table>';
            if ((data.unreadable || []).length) {
                html += '<p class="muted" style="margin-top:12px">No legibles: ' + esc(data.unreadable.join(', ')) + '</p>';
            }
            html += '</div>';
        }

        // raw siempre disponible para revisión
        html += '<div class="card"><details><summary>Ver respuesta cruda de la IA (raw)</summary><pre>' +
                esc(data.raw || '(vacío)') + '</pre></details></div>';

        out.innerHTML = html;
    }
</script>
</body>
</html>
