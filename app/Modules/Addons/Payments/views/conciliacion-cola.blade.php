<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cola de conciliación</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1250px; margin: 0 auto; padding: 18px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 14px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .tab { padding: 8px 16px; border-radius: 8px; border: 1px solid #334155; background: #1e293b; cursor: pointer; font-size: 14px; }
        .tab.on { border-color: #06b6d4; background: #0b2b33; color: #67e8f9; }
        .tab .n { display: inline-block; margin-left: 6px; background: #334155; color: #e2e8f0; border-radius: 999px; padding: 0 7px; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 14px; }
        .list { max-height: 76vh; overflow: auto; }
        .item { padding: 10px 12px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 8px; cursor: pointer; font-size: 13px; }
        .item:hover { border-color: #06b6d4; } .item.sel { border-color: #06b6d4; background: #0b2b33; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .b-prop { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
        .b-multi { background: #3b0764; color: #d8b4fe; border: 1px solid #7e22ce; }
        .b-esc { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
        .muted { color: #64748b; font-size: 12px; }
        .preview { min-height: 240px; display: flex; align-items: center; justify-content: center; background: #0b1220; border: 1px solid #334155; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
        .preview img { max-width: 100%; max-height: 62vh; } .preview iframe { width: 100%; height: 62vh; border: 0; }
        table { width: 100%; border-collapse: collapse; } td, th { text-align: left; padding: 6px 8px; border-bottom: 1px solid #334155; font-size: 14px; }
        th { color: #94a3b8; font-size: 12px; text-transform: uppercase; width: 38%; }
        input, textarea { width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #e2e8f0; font-size: 14px; }
        button { border: 0; border-radius: 8px; padding: 9px 16px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .ok { background: #16a34a; color: #fff; } .no { background: #b91c1c; color: #fff; } .ghost { background: #334155; color: #e2e8f0; }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .row { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
        .svc { display: block; padding: 7px 10px; border: 1px solid #334155; border-radius: 8px; margin: 4px 0; cursor: pointer; font-size: 13px; }
        .svc.on { border-color: #06b6d4; background: #0b2b33; }
        .note { color: #94a3b8; font-size: 12px; margin-top: 8px; }
        .warn { background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: 12px; padding: 8px 10px; border-radius: 8px; margin: 10px 0; }
        .res { margin-top: 10px; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Cola de conciliación</h1>
    <p class="sub">Revisa y confirma los pagos por WhatsApp. Confirmar aplica el pago (como MEGAISP, confirmado por ti). No se notifica al cliente por WhatsApp todavía.</p>

    <div class="tabs">
        <div class="tab on" data-type="propuesto" onclick="switchTab('propuesto')">Propuestos <span class="n" id="n-propuesto">{{ $counts['propuesto'] }}</span></div>
        <div class="tab" data-type="escalado" onclick="switchTab('escalado')">Escalados <span class="n" id="n-escalado">{{ $counts['escalado'] }}</span></div>
        <div class="tab" data-type="verificacion" onclick="switchTab('verificacion')">Verificación bancaria <span class="n" id="n-verificacion">{{ $counts['verificacion'] }}</span></div>
    </div>

    <div class="grid">
        <div class="card list" id="list"><p class="muted">Cargando…</p></div>
        <div class="card"><div id="detail"><p class="muted">Selecciona un caso de la lista.</p></div></div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const U = "{{ url('finanzas/conciliacion-cola') }}";
let tab = 'propuesto', current = null, chosenService = null, chosenClient = null;

function esc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
async function get(u){ return (await fetch(u, {headers:{'Accept':'application/json'}})).json(); }
async function post(u,b){ const r = await fetch(u,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(b||{})}); return {status:r.status, data: await r.json().catch(()=>({}))}; }

function switchTab(t){
    tab = t; current = null;
    document.querySelectorAll('.tab').forEach(el => el.classList.toggle('on', el.dataset.type === t));
    document.getElementById('detail').innerHTML = '<p class="muted">Selecciona un caso de la lista.</p>';
    loadList();
}

async function loadList(){
    const d = await get(U + '/list?type=' + tab);
    const box = document.getElementById('list');
    if (tab === 'verificacion'){
        box.innerHTML = d.rows.length ? d.rows.map(r =>
            `<div class="item"><b>$${esc(r.amount)}</b> · cliente ${esc(r.client_id)}<div class="muted">clave ${esc(r.clave_rastreo)} · ${esc(r.fecha_pago)}</div></div>`).join('')
            : '<p class="muted">Sin pagos pendientes de verificación bancaria.</p>';
        return;
    }
    box.innerHTML = d.rows.length ? d.rows.map(r => {
        const b = r.multiple_services ? '<span class="badge b-multi">multi-servicio</span>'
                : (tab === 'escalado' ? '<span class="badge b-esc">escalado</span>' : '<span class="badge b-prop">propuesto</span>');
        const who = r.client ? esc(r.client.name) : '<i>sin identificar</i>';
        return `<div class="item" data-id="${r.id}" onclick="selectCase(${r.id})">
            <div>${b} <b>$${esc(r.monto ?? '?')}</b></div>
            <div style="margin-top:3px;">${who}</div>
            <div class="muted">clave ${esc(r.clave_rastreo ?? '—')} · ${esc(r.banco ?? '')} · ${esc(r.created_at ?? '')}</div>
        </div>`;
    }).join('') : '<p class="muted">Sin casos en esta pestaña. 🎉</p>';
}

async function selectCase(id){
    current = id; chosenService = null; chosenClient = null;
    document.querySelectorAll('.item').forEach(el => el.classList.toggle('sel', +el.dataset.id === id));
    const s = await get(U + '/' + id + '/detalle');
    const f = s.fields || {};
    const fld = k => (f[k] && f[k].value) ? esc(f[k].value) : '<span class="muted">—</span>';

    let media = s.has_media
        ? (s.media_ext === 'pdf' ? `<iframe src="${U}/${id}/media"></iframe>` : `<img src="${U}/${id}/media">`)
        : '<span class="muted">Sin comprobante</span>';

    let clientBlock, actions;
    if (s.client) {
        clientBlock = `<tr><th>Cliente propuesto</th><td><b>${esc(s.client.name)}</b> (id ${s.client.id})</td></tr>
                       <tr><th>Identificado por</th><td>${esc(s.method || '—')} · certeza ${esc(s.certainty || '—')}</td></tr>`;
    } else {
        clientBlock = `<tr><th>Cliente</th><td><i>Sin identificar — búscalo abajo</i></td></tr>`;
    }

    // multi-servicio → elegir
    let svcBlock = '';
    if (s.multiple_services && s.services && s.services.length){
        svcBlock = '<div class="warn">Este cliente tiene varios servicios. Elige a cuál aplicar antes de confirmar:</div>' +
            s.services.map(sv => `<div class="svc" data-svc="${sv.id}" onclick="pickService(${sv.id}, this)">${esc(sv.type)}: ${esc(sv.description)}</div>`).join('');
    }

    // escalado sin cliente → buscador
    let searchBlock = '';
    if (!s.client){
        searchBlock = `<div class="row"><input id="cq" placeholder="Busca por nombre o número de cliente" onkeydown="if(event.key==='Enter')doSearch()">
            <button class="ghost" onclick="doSearch()">Buscar</button></div><div id="cres" class="res"></div>`;
    }

    const canConfirm = s.client || false; // si no hay cliente, se habilita al elegir uno
    actions = `<div class="row">
        <button class="ok" id="btnOk" ${canConfirm ? '' : 'disabled'} onclick="confirmCase()">Confirmar y aplicar</button>
        <button class="no" onclick="rejectCase()">Rechazar</button>
    </div><div id="actionRes" class="res"></div>
    <p class="note">Confirmar respeta el anti-duplicado de Fase 4. No se envía WhatsApp al cliente.</p>`;

    document.getElementById('detail').innerHTML =
        `<div class="preview">${media}</div>
         <table>
            <tr><th>Monto</th><td><b>$${fld('monto')}</b></td></tr>
            <tr><th>Clave de rastreo</th><td>${fld('clave_rastreo')}</td></tr>
            <tr><th>Concepto</th><td>${fld('concepto')}</td></tr>
            <tr><th>Banco origen</th><td>${fld('banco_origen')}</td></tr>
            <tr><th>Titular</th><td>${fld('titular_ordenante')}</td></tr>
            ${clientBlock}
         </table>${svcBlock}${searchBlock}${actions}`;
}

function pickService(id, el){
    chosenService = id;
    document.querySelectorAll('.svc').forEach(s => s.classList.toggle('on', s === el));
}

async function doSearch(){
    const q = document.getElementById('cq').value.trim();
    if (!q) return;
    const d = await get(U + '/clientes/buscar?q=' + encodeURIComponent(q));
    document.getElementById('cres').innerHTML = d.rows.length
        ? d.rows.map(r => `<div class="svc" onclick="pickClient(${r.client_id}, this)">${esc(r.name)} (id ${r.client_id})${r.colonia?' · '+esc(r.colonia):''}</div>`).join('')
        : '<span class="muted">Sin coincidencias.</span>';
}
function pickClient(id, el){
    chosenClient = id;
    document.querySelectorAll('#cres .svc').forEach(s => s.classList.toggle('on', s === el));
    document.getElementById('btnOk').disabled = false;
}

async function confirmCase(){
    if (!current) return;
    document.getElementById('btnOk').disabled = true;
    const body = {};
    if (chosenService) body.service_id = chosenService;
    if (chosenClient) body.client_id = chosenClient;
    const {status, data} = await post(U + '/' + current + '/confirmar', body);
    const el = document.getElementById('actionRes');
    if (status === 200 && data.applied){
        el.innerHTML = `<span style="color:#4ade80;">✓ Aplicado. Pago #${data.payment_id} (confirmado por ti).</span>`;
        setTimeout(() => { refreshCounts(); loadList(); document.getElementById('detail').innerHTML = '<p class="muted">Caso confirmado. Selecciona otro.</p>'; }, 900);
    } else {
        el.innerHTML = `<span style="color:#f87171;">No se aplicó: ${esc(data.reason || data.message || 'error')}.</span>`;
        document.getElementById('btnOk').disabled = false;
    }
}

async function rejectCase(){
    if (!current) return;
    const reason = prompt('Motivo del rechazo (opcional):') || '';
    const {status} = await post(U + '/' + current + '/rechazar', {reason});
    if (status === 200){ refreshCounts(); loadList(); document.getElementById('detail').innerHTML = '<p class="muted">Caso rechazado. Selecciona otro.</p>'; }
}

async function refreshCounts(){
    for (const t of ['propuesto','escalado','verificacion']){
        const d = await get(U + '/list?type=' + t);
        document.getElementById('n-' + t).textContent = d.rows.length;
    }
}

loadList();
</script>
</body>
</html>
