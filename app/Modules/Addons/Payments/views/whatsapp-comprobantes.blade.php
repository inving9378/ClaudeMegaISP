<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comprobantes por WhatsApp — Extracción IA</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 20px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 18px; }
        .crit { color: #f59e0b; font-size: 12px; margin: 0 0 16px; }
        .grid { display: grid; grid-template-columns: 340px 1fr; gap: 18px; align-items: start; }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 14px; }
        .list { max-height: 78vh; overflow: auto; }
        .item { padding: 10px 12px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 8px; cursor: pointer; font-size: 13px; }
        .item:hover { border-color: #06b6d4; }
        .item.sel { border-color: #06b6d4; background: #0b2b33; }
        .item .top { display: flex; justify-content: space-between; gap: 8px; }
        .pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .pill-img { background: #082f49; color: #38bdf8; border: 1px solid #0e7490; }
        .pill-pdf { background: #3b0764; color: #d8b4fe; border: 1px solid #7e22ce; }
        .done { color: #4ade80; font-size: 11px; }
        .muted { color: #64748b; font-size: 12px; }
        button { background: #06b6d4; color: #04222b; font-weight: 600; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-size: 14px; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .preview { min-height: 320px; display: flex; align-items: center; justify-content: center; background: #0b1220; border: 1px solid #334155; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
        .preview img { max-width: 100%; max-height: 70vh; display: block; }
        .preview embed, .preview iframe { width: 100%; height: 72vh; border: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; font-size: 14px; vertical-align: top; }
        th { color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .val-null { color: #f87171; font-style: italic; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .conf-alta { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .conf-media { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
        .conf-baja { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
        .err { background: #450a0a; border: 1px solid #991b1b; color: #fecaca; padding: 12px; border-radius: 8px; }
        pre { background: #0b1220; border: 1px solid #334155; border-radius: 8px; padding: 12px; overflow: auto; font-size: 12px; color: #cbd5e1; white-space: pre-wrap; }
        details { margin-top: 12px; }
        summary { cursor: pointer; color: #94a3b8; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Comprobantes recibidos por WhatsApp</h1>
    <p class="sub">Selecciona un comprobante, míralo y pulsa <b>Extraer con IA</b>. Acepta imagen y PDF.</p>
    <p class="crit">⚠️ Alcance: extrae y, ya extraído, puede identificar al cliente (F3). No aplica pago (F4). Ambos disparos son manuales.</p>

    <div class="grid">
        <div class="card list">
            @forelse ($rows as $r)
                <div class="item" data-id="{{ $r['id'] }}" onclick="selectItem({{ $r['id'] }})">
                    <div class="top">
                        <span>
                            <span class="pill {{ $r['type'] === 'document' ? 'pill-pdf' : 'pill-img' }}">{{ strtoupper($r['ext'] ?: $r['type']) }}</span>
                            #{{ $r['id'] }}
                        </span>
                        <span class="muted">{{ $r['received_at'] }}</span>
                    </div>
                    <div class="muted" style="margin-top:4px;">
                        conv {{ $r['conversation_id'] ?? '—' }}
                        @if ($r['extracted'])
                            · <span class="done">✓ extraído{{ $r['extracted_ok'] ? '' : ' (con error)' }}</span>
                        @endif
                    </div>
                    @if (!empty($r['caption']))
                        <div class="muted" style="margin-top:4px;">“{{ \Illuminate\Support\Str::limit($r['caption'], 50) }}”</div>
                    @endif
                </div>
            @empty
                <p class="muted">No hay comprobantes (imagen/PDF) recibidos por WhatsApp todavía.</p>
            @endforelse
        </div>

        <div class="card">
            <div id="preview" class="preview"><span class="muted">Selecciona un comprobante de la lista.</span></div>
            <button id="btnExtract" onclick="extract()" disabled>Extraer con IA</button>
            <button id="btnIdentify" onclick="identify()" disabled>Identificar cliente</button>
            <span id="status" class="muted" style="margin-left:10px;"></span>
            <div id="result" style="margin-top:16px;"></div>
            <div id="identifyResult" style="margin-top:10px;"></div>
        </div>
    </div>
</div>

<script>
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const MEDIA = "{{ url('finanzas/whatsapp-comprobantes') }}";
    let current = null;

    function selectItem(id) {
        current = id;
        document.querySelectorAll('.item').forEach(el => el.classList.toggle('sel', +el.dataset.id === id));
        const ext = document.querySelector('.item[data-id="'+id+'"] .pill').textContent.trim().toLowerCase();
        const url = MEDIA + '/' + id + '/media';
        const box = document.getElementById('preview');
        if (ext === 'pdf') {
            box.innerHTML = '<iframe src="'+url+'"></iframe>';
        } else {
            box.innerHTML = '<img src="'+url+'" alt="comprobante">';
        }
        document.getElementById('btnExtract').disabled = false;
        document.getElementById('btnIdentify').disabled = true;
        document.getElementById('result').innerHTML = '';
        document.getElementById('identifyResult').innerHTML = '';
        document.getElementById('status').textContent = '';
    }

    async function extract() {
        if (!current) return;
        const btn = document.getElementById('btnExtract');
        btn.disabled = true;
        document.getElementById('status').textContent = 'Extrayendo…';
        try {
            const res = await fetch(MEDIA + '/' + current + '/extraer', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const data = await res.json();
            render(data);
            document.getElementById('status').textContent = 'Extraído ' + (data.extracted_at || '');
            document.getElementById('btnIdentify').disabled = !(data.result && data.result.ok);
        } catch (e) {
            document.getElementById('result').innerHTML = '<div class="err">Error de red: ' + e.message + '</div>';
            document.getElementById('status').textContent = '';
        } finally {
            btn.disabled = false;
        }
    }

    // Item #204 FASE A — dispara F3 (identificación) sobre la extracción ya hecha.
    async function identify() {
        if (!current) return;
        const btn = document.getElementById('btnIdentify');
        btn.disabled = true;
        const box = document.getElementById('identifyResult');
        box.innerHTML = '<span class="muted">Identificando…</span>';
        try {
            const res = await fetch(MEDIA + '/' + current + '/identificar', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                box.innerHTML = '<div class="err">' + esc(err.message || ('HTTP ' + res.status)) + '</div>';
                return;
            }
            const data = await res.json();
            const labels = {
                created: 'Sesión de identificación iniciada',
                already_exists: 'Ya existía una sesión para este comprobante',
                duplicate_clave: '⚠️ Clave/referencia ya vista en OTRA conversación — escalado como posible duplicidad',
            };
            box.innerHTML = '<div class="done">' + esc(labels[data.status] || data.status)
                + ' — sesión #' + esc(data.session_id) + ', estado: ' + esc(data.state) + '</div>';
        } catch (e) {
            box.innerHTML = '<div class="err">Error de red: ' + e.message + '</div>';
        } finally {
            btn.disabled = false;
        }
    }

    function esc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

    // Etiquetas legibles (para distinguir claramente titular vs concepto).
    const LABELS = {
        clave_rastreo:     'Clave de rastreo',
        monto:             'Monto',
        fecha_pago:        'Fecha del pago',
        titular_ordenante: 'Titular ordenante (dueño de la cuenta)',
        banco_origen:      'Banco origen',
        concepto:          'Concepto / referencia (lo escribió el pagador)',
    };

    function render(data) {
        const r = data.result || {};
        const box = document.getElementById('result');
        if (!r.ok) {
            box.innerHTML = '<div class="err"><b>La IA no pudo extraer.</b><br>' + esc(r.error || 'Sin detalle') + '</div>' + rawBlock(r.raw);
            return;
        }
        let rows = '';
        for (const [k, v] of Object.entries(r.fields || {})) {
            const val = (v.value === null || v.value === undefined)
                ? '<span class="val-null">— ilegible —</span>' : esc(v.value);
            const conf = (v.confidence || 'baja');
            const label = LABELS[k] || k;
            rows += '<tr><td>' + esc(label) + '</td><td>' + val + '</td><td><span class="badge conf-' + conf + '">' + conf + '</span></td></tr>';
        }
        let unreadable = (r.unreadable && r.unreadable.length)
            ? '<p class="muted">Ilegibles: ' + r.unreadable.map(esc).join(', ') + '</p>' : '';
        box.innerHTML =
            '<table><thead><tr><th>Campo</th><th>Valor leído</th><th>Confianza</th></tr></thead><tbody>' + rows + '</tbody></table>'
            + unreadable + rawBlock(r.raw);
    }

    function rawBlock(raw) {
        if (!raw) return '';
        return '<details><summary>Respuesta cruda de la IA (auditoría)</summary><pre>' + esc(raw) + '</pre></details>';
    }
</script>
</body>
</html>
