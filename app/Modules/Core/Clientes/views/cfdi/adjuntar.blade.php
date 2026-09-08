<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Adjuntar CFDI a factura</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 820px; margin: 0 auto; padding: 24px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
        label { display: block; font-size: 13px; margin: 0 0 6px; color: #cbd5e1; }
        input[type=text], input[type=file] { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #e2e8f0; margin-bottom: 14px; }
        button { background: #06b6d4; color: #04222b; font-weight: 600; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-size: 14px; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .warn { background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: 12px; padding: 8px 10px; border-radius: 8px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; font-size: 14px; vertical-align: top; }
        th { color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr.row { cursor: pointer; }
        tr.row:hover { background: #0f172a; }
        tr.row.selected { background: #0e3a4a; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge-ok { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .badge-none { background: #1e293b; color: #64748b; border: 1px solid #334155; }
        .err { background: #450a0a; border: 1px solid #991b1b; color: #fecaca; padding: 12px; border-radius: 8px; }
        .ok { background: #052e16; border: 1px solid #166534; color: #86efac; padding: 12px; border-radius: 8px; }
        .muted { color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Adjuntar CFDI a factura</h1>
    <p class="sub">Sube el XML y PDF de un CFDI que ya fue timbrado fuera del sistema. Se ligan a la factura para que el cliente los vea/descargue en su Portal.</p>

    <div class="card">
        <div class="warn">Herramienta interna (super-administrator / DESARROLLADOR). No timbra ni genera facturas: solo guarda archivos ya timbrados y los liga a la factura correcta.</div>

        <label for="numero">Buscar factura por número</label>
        <input type="text" id="numero" placeholder="Ej. 1234">
        <div id="resultados"></div>
    </div>

    <div class="card" id="form-adjuntar" style="display:none">
        <p><strong>Factura seleccionada:</strong> <span id="factura-info"></span></p>

        <label for="xml">XML del CFDI</label>
        <input type="file" id="xml" accept=".xml,text/xml,application/xml">

        <label for="pdf">PDF del CFDI</label>
        <input type="file" id="pdf" accept="application/pdf">

        <button id="btn-adjuntar" type="button">Adjuntar CFDI</button>
    </div>

    <div id="mensaje"></div>
</div>

<script>
    const BUSCAR_URL = @json(route('facturacion.cfdi.buscar'));
    const ADJUNTAR_URL_BASE = @json(url('/facturacion/cfdi'));
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    const numeroInput = document.getElementById('numero');
    const resultados = document.getElementById('resultados');
    const formAdjuntar = document.getElementById('form-adjuntar');
    const facturaInfo = document.getElementById('factura-info');
    const mensaje = document.getElementById('mensaje');
    const btnAdjuntar = document.getElementById('btn-adjuntar');

    let facturaSeleccionada = null;
    let buscarTimeout = null;

    numeroInput.addEventListener('input', () => {
        clearTimeout(buscarTimeout);
        const termino = numeroInput.value.trim();
        if (termino.length < 1) { resultados.innerHTML = ''; return; }
        buscarTimeout = setTimeout(() => buscar(termino), 350);
    });

    async function buscar(termino) {
        resultados.innerHTML = '<p class="muted">Buscando…</p>';
        try {
            const resp = await fetch(BUSCAR_URL + '?numero=' + encodeURIComponent(termino), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await resp.json();
            renderResultados(data.data || []);
        } catch (e) {
            resultados.innerHTML = '<div class="err">Error de red: ' + esc(e.message) + '</div>';
        }
    }

    function renderResultados(facturas) {
        if (!facturas.length) {
            resultados.innerHTML = '<p class="muted">Sin resultados.</p>';
            return;
        }
        let html = '<table><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Estado</th><th>CFDI</th></tr></thead><tbody>';
        for (const f of facturas) {
            html += '<tr class="row" data-id="' + f.id + '" data-info="' + esc('#' + f.number + ' — ' + f.client_name + ' — $' + f.total) + '">' +
                '<td>' + esc(f.number) + '</td>' +
                '<td>' + esc(f.client_name) + '</td>' +
                '<td>$' + esc(f.total) + '</td>' +
                '<td>' + esc(f.estado) + '</td>' +
                '<td>' + (f.tiene_cfdi ? '<span class="badge badge-ok">ya tiene</span>' : '<span class="badge badge-none">sin CFDI</span>') + '</td>' +
                '</tr>';
        }
        html += '</tbody></table>';
        resultados.innerHTML = html;

        resultados.querySelectorAll('tr.row').forEach(tr => {
            tr.addEventListener('click', () => {
                resultados.querySelectorAll('tr.row').forEach(x => x.classList.remove('selected'));
                tr.classList.add('selected');
                facturaSeleccionada = tr.dataset.id;
                facturaInfo.textContent = tr.dataset.info;
                formAdjuntar.style.display = 'block';
                mensaje.innerHTML = '';
            });
        });
    }

    btnAdjuntar.addEventListener('click', async () => {
        if (!facturaSeleccionada) return;
        const xml = document.getElementById('xml').files[0];
        const pdf = document.getElementById('pdf').files[0];
        if (!xml || !pdf) {
            mensaje.innerHTML = '<div class="err">Selecciona el XML y el PDF.</div>';
            return;
        }

        btnAdjuntar.disabled = true;
        mensaje.innerHTML = '<p class="muted">Subiendo…</p>';

        const fd = new FormData();
        fd.append('xml', xml);
        fd.append('pdf', pdf);

        try {
            const resp = await fetch(ADJUNTAR_URL_BASE + '/' + facturaSeleccionada + '/adjuntar', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await resp.json();
            if (!resp.ok) {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : ('Error HTTP ' + resp.status));
                mensaje.innerHTML = '<div class="err">' + esc(msg) + '</div>';
                return;
            }
            mensaje.innerHTML = '<div class="ok">' + esc(data.message) + (data.data.uuid_fiscal ? ' UUID: ' + esc(data.data.uuid_fiscal) : ' (UUID no detectado en el XML, se guardó igual)') + '</div>';
        } catch (e) {
            mensaje.innerHTML = '<div class="err">Error de red: ' + esc(e.message) + '</div>';
        } finally {
            btnAdjuntar.disabled = false;
        }
    });
</script>
</body>
</html>
