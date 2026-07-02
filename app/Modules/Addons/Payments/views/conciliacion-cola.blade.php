@extends('core-layout::master')
@section('title') Cola de conciliación @endsection

@section('styles')
<style>
    /* Scopeado bajo .cc-wrap para NO afectar el layout/sidebar del sistema. */
    .cc-wrap { padding: 6px 2px 40px; color: #e2e8f0; }
    .cc-wrap h1 { font-size: 20px; margin: 0 0 4px; color: #e2e8f0; }
    .cc-wrap .sub { color: #94a3b8; font-size: 13px; margin: 0 0 14px; }
    .cc-wrap .tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .cc-wrap .tab { padding: 8px 16px; border-radius: 8px; border: 1px solid #334155; background: #1e293b; cursor: pointer; font-size: 14px; }
    .cc-wrap .tab.on { border-color: #06b6d4; background: #0b2b33; color: #67e8f9; }
    .cc-wrap .tab .n { display: inline-block; margin-left: 6px; background: #334155; color: #e2e8f0; border-radius: 999px; padding: 0 7px; font-size: 12px; }
    .cc-wrap .grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .cc-wrap .grid { grid-template-columns: 1fr; } }
    .cc-wrap .cc-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 14px; }
    .cc-wrap .cc-list { max-height: 74vh; overflow: auto; }
    .cc-wrap .item { padding: 10px 12px; border: 1px solid #334155; border-radius: 8px; margin-bottom: 8px; cursor: pointer; font-size: 13px; }
    .cc-wrap .item:hover { border-color: #06b6d4; } .cc-wrap .item.sel { border-color: #06b6d4; background: #0b2b33; }
    .cc-wrap .cc-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .cc-wrap .b-prop { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
    .cc-wrap .b-multi { background: #3b0764; color: #d8b4fe; border: 1px solid #7e22ce; }
    .cc-wrap .b-esc { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
    .cc-wrap .muted { color: #64748b; font-size: 12px; }
    .cc-wrap .cc-prev { min-height: 240px; display: flex; align-items: center; justify-content: center; background: #0b1220; border: 1px solid #334155; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
    .cc-wrap .cc-prev img { max-width: 100%; max-height: 60vh; } .cc-wrap .cc-prev iframe { width: 100%; height: 60vh; border: 0; }
    .cc-wrap table.cc-tbl { width: 100%; border-collapse: collapse; } .cc-wrap table.cc-tbl td, .cc-wrap table.cc-tbl th { text-align: left; padding: 6px 8px; border-bottom: 1px solid #334155; font-size: 14px; color: #e2e8f0; }
    .cc-wrap table.cc-tbl th { color: #94a3b8; font-size: 12px; text-transform: uppercase; width: 38%; }
    .cc-wrap .cc-in { width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #e2e8f0; font-size: 14px; }
    .cc-wrap .cc-btn { border: 0; border-radius: 8px; padding: 9px 16px; cursor: pointer; font-size: 14px; font-weight: 600; }
    .cc-wrap .cc-ok { background: #16a34a; color: #fff; } .cc-wrap .cc-no { background: #b91c1c; color: #fff; } .cc-wrap .cc-ghost { background: #334155; color: #e2e8f0; }
    .cc-wrap .cc-btn:disabled { opacity: .45; cursor: not-allowed; }
    .cc-wrap .row { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
    .cc-wrap .svc { display: block; padding: 7px 10px; border: 1px solid #334155; border-radius: 8px; margin: 4px 0; cursor: pointer; font-size: 13px; }
    .cc-wrap .svc.on { border-color: #06b6d4; background: #0b2b33; }
    .cc-wrap .note { color: #94a3b8; font-size: 12px; margin-top: 8px; }
    .cc-wrap .warn { background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: 12px; padding: 8px 10px; border-radius: 8px; margin: 10px 0; }
    .cc-wrap .res { margin-top: 10px; font-size: 13px; }
</style>
@endsection

@section('content')
<div class="cc-wrap">
    <h1>Cola de conciliación</h1>
    <p class="sub">Revisa y confirma los pagos por WhatsApp. Confirmar aplica el pago (como MEGAISP, confirmado por ti). No se notifica al cliente por WhatsApp todavía.</p>

    <div class="tabs">
        <div class="tab on" data-type="propuesto" onclick="ccSwitch('propuesto')">Propuestos <span class="n" id="cc-n-propuesto">{{ $counts['propuesto'] }}</span></div>
        <div class="tab" data-type="escalado" onclick="ccSwitch('escalado')">Escalados <span class="n" id="cc-n-escalado">{{ $counts['escalado'] }}</span></div>
        <div class="tab" data-type="verificacion" onclick="ccSwitch('verificacion')">Verificación bancaria <span class="n" id="cc-n-verificacion">{{ $counts['verificacion'] }}</span></div>
    </div>

    <div class="grid">
        <div class="cc-card cc-list" id="cc-list"><p class="muted">Cargando…</p></div>
        <div class="cc-card"><div id="cc-detail"><p class="muted">Selecciona un caso de la lista.</p></div></div>
    </div>
</div>

<script>
(function(){
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const U = "{{ url('finanzas/conciliacion-cola') }}";
    let tab = 'propuesto', current = null, chosenService = null, chosenClient = null;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const get = async u => (await fetch(u, {headers:{'Accept':'application/json'}})).json();
    async function post(u,b){ const r = await fetch(u,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(b||{})}); return {status:r.status, data: await r.json().catch(()=>({}))}; }

    window.ccSwitch = function(t){
        tab = t; current = null;
        document.querySelectorAll('.cc-wrap .tab').forEach(el => el.classList.toggle('on', el.dataset.type === t));
        document.getElementById('cc-detail').innerHTML = '<p class="muted">Selecciona un caso de la lista.</p>';
        loadList();
    };

    async function loadList(){
        const d = await get(U + '/list?type=' + tab);
        const box = document.getElementById('cc-list');
        if (tab === 'verificacion'){
            box.innerHTML = d.rows.length ? d.rows.map(r =>
                `<div class="item"><b>$${esc(r.amount)}</b> · cliente ${esc(r.client_id)}<div class="muted">clave ${esc(r.clave_rastreo)} · ${esc(r.fecha_pago)}</div></div>`).join('')
                : '<p class="muted">Sin pagos pendientes de verificación bancaria.</p>';
            return;
        }
        box.innerHTML = d.rows.length ? d.rows.map(r => {
            const b = r.multiple_services ? '<span class="cc-badge b-multi">multi-servicio</span>'
                    : (tab === 'escalado' ? '<span class="cc-badge b-esc">escalado</span>' : '<span class="cc-badge b-prop">propuesto</span>');
            const who = r.client ? esc(r.client.name) : '<i>sin identificar</i>';
            return `<div class="item" data-id="${r.id}" onclick="ccSelect(${r.id})">
                <div>${b} <b>$${esc(r.monto ?? '?')}</b></div>
                <div style="margin-top:3px;">${who}</div>
                <div class="muted">clave ${esc(r.clave_rastreo ?? '—')} · ${esc(r.banco ?? '')} · ${esc(r.fecha_pago ?? r.created_at ?? '')}</div>
            </div>`;
        }).join('') : '<p class="muted">Sin casos en esta pestaña. 🎉</p>';
    }

    window.ccSelect = async function(id){
        current = id; chosenService = null; chosenClient = null;
        document.querySelectorAll('.cc-wrap .item').forEach(el => el.classList.toggle('sel', +el.dataset.id === id));
        const s = await get(U + '/' + id + '/detalle');
        const f = s.fields || {};
        const fld = k => (f[k] && f[k].value) ? esc(f[k].value) : '<span class="muted">—</span>';

        let media = s.has_media
            ? (s.media_ext === 'pdf' ? `<iframe src="${U}/${id}/media"></iframe>` : `<img src="${U}/${id}/media">`)
            : '<span class="muted">Sin comprobante</span>';

        let clientBlock = s.client
            ? `<tr><th>Cliente propuesto</th><td><b>${esc(s.client.name)}</b> (id ${s.client.id})</td></tr>
               <tr><th>Identificado por</th><td>${esc(s.method || '—')} · certeza ${esc(s.certainty || '—')}</td></tr>`
            : `<tr><th>Cliente</th><td><i>Sin identificar — búscalo abajo</i></td></tr>`;

        let svcBlock = '';
        if (s.multiple_services && s.services && s.services.length){
            svcBlock = '<div class="warn">Este cliente tiene varios servicios. Elige a cuál aplicar antes de confirmar:</div>' +
                s.services.map(sv => `<div class="svc" data-svc="${sv.id}" onclick="ccPickService(${sv.id}, this)">${esc(sv.type)}: ${esc(sv.description)}</div>`).join('');
        }

        let searchBlock = '';
        if (!s.client){
            searchBlock = `<div class="row"><input class="cc-in" id="cc-q" placeholder="Busca por nombre o número de cliente" onkeydown="if(event.key==='Enter')ccSearch()">
                <button class="cc-btn cc-ghost" onclick="ccSearch()">Buscar</button></div><div id="cc-res" class="res"></div>`;
        }

        const canConfirm = !!s.client;
        let actions = `<div class="row">
            <button class="cc-btn cc-ok" id="cc-ok" ${canConfirm ? '' : 'disabled'} onclick="ccConfirm()">Confirmar y aplicar</button>
            <button class="cc-btn cc-no" onclick="ccReject()">Rechazar</button>
        </div><div id="cc-actionRes" class="res"></div>
        <p class="note">Confirmar respeta el anti-duplicado de Fase 4. No se envía WhatsApp al cliente.</p>`;

        document.getElementById('cc-detail').innerHTML =
            `<div class="cc-prev">${media}</div>
             <table class="cc-tbl">
                <tr><th>Monto</th><td><b>$${fld('monto')}</b></td></tr>
                <tr><th>Fecha del pago</th><td>${fld('fecha_pago')}</td></tr>
                <tr><th>Clave de rastreo</th><td>${fld('clave_rastreo')}</td></tr>
                <tr><th>Concepto</th><td>${fld('concepto')}</td></tr>
                <tr><th>Banco origen</th><td>${fld('banco_origen')}</td></tr>
                <tr><th>Titular</th><td>${fld('titular_ordenante')}</td></tr>
                ${clientBlock}
             </table>${svcBlock}${searchBlock}${actions}`;
    };

    window.ccPickService = function(id, el){
        chosenService = id;
        document.querySelectorAll('.cc-wrap .svc').forEach(s => s.classList.toggle('on', s === el));
    };

    window.ccSearch = async function(){
        const q = document.getElementById('cc-q').value.trim(); if (!q) return;
        const d = await get(U + '/clientes/buscar?q=' + encodeURIComponent(q));
        document.getElementById('cc-res').innerHTML = d.rows.length
            ? d.rows.map(r => `<div class="svc" onclick="ccPickClient(${r.client_id}, this)">${esc(r.name)} (id ${r.client_id})${r.colonia?' · '+esc(r.colonia):''}</div>`).join('')
            : '<span class="muted">Sin coincidencias.</span>';
    };
    window.ccPickClient = function(id, el){
        chosenClient = id;
        document.querySelectorAll('#cc-res .svc').forEach(s => s.classList.toggle('on', s === el));
        document.getElementById('cc-ok').disabled = false;
    };

    window.ccConfirm = async function(){
        if (!current) return;
        document.getElementById('cc-ok').disabled = true;
        const body = {};
        if (chosenService) body.service_id = chosenService;
        if (chosenClient) body.client_id = chosenClient;
        const {status, data} = await post(U + '/' + current + '/confirmar', body);
        const el = document.getElementById('cc-actionRes');
        if (status === 200 && data.applied){
            el.innerHTML = `<span style="color:#4ade80;">✓ Aplicado. Pago #${data.payment_id} (confirmado por ti).</span>`;
            setTimeout(() => { refreshCounts(); loadList(); document.getElementById('cc-detail').innerHTML = '<p class="muted">Caso confirmado. Selecciona otro.</p>'; }, 900);
        } else {
            el.innerHTML = `<span style="color:#f87171;">No se aplicó: ${esc(data.reason || data.message || 'error')}.</span>`;
            document.getElementById('cc-ok').disabled = false;
        }
    };

    window.ccReject = async function(){
        if (!current) return;
        const reason = prompt('Motivo del rechazo (opcional):') || '';
        const {status} = await post(U + '/' + current + '/rechazar', {reason});
        if (status === 200){ refreshCounts(); loadList(); document.getElementById('cc-detail').innerHTML = '<p class="muted">Caso rechazado. Selecciona otro.</p>'; }
    };

    async function refreshCounts(){
        for (const t of ['propuesto','escalado','verificacion']){
            const d = await get(U + '/list?type=' + t);
            const el = document.getElementById('cc-n-' + t); if (el) el.textContent = d.rows.length;
        }
    }

    loadList();
})();
</script>
@endsection
