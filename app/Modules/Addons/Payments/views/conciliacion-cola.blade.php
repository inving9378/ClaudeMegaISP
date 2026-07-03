@extends('core-layout::master')
@section('title') Cola de conciliación @endsection

@section('styles')
<style>
    /* Scopeado bajo .cc-wrap + 100% con tokens de dark-light-tokens.css → sigue el
       tema claro/oscuro del sistema, nada hardcoded. */
    .cc-wrap { padding: 6px 2px 40px; color: var(--text-primary); }
    .cc-wrap h1 { font-size: 20px; margin: 0 0 4px; color: var(--text-primary); }
    .cc-wrap .sub { color: var(--text-secondary); font-size: 13px; margin: 0 0 14px; }
    .cc-wrap .tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .cc-wrap .tab { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-default); background: var(--bg-surface); color: var(--text-primary); cursor: pointer; font-size: 14px; }
    .cc-wrap .tab.on { border-color: var(--accent); color: var(--accent); }
    .cc-wrap .tab .n { display: inline-block; margin-left: 6px; background: var(--bg-hover); color: var(--text-primary); border-radius: 999px; padding: 0 7px; font-size: 12px; }
    .cc-wrap .grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .cc-wrap .grid { grid-template-columns: 1fr; } }
    .cc-wrap .cc-card { background: var(--bg-surface); border: 1px solid var(--border-default); border-radius: 10px; padding: 14px; box-shadow: var(--shadow-card); }
    .cc-wrap .cc-list { max-height: 74vh; overflow: auto; }
    .cc-wrap .item { padding: 10px 12px; border: 1px solid var(--border-default); border-radius: 8px; margin-bottom: 8px; cursor: pointer; font-size: 13px; background: var(--bg-primary); }
    .cc-wrap .item:hover { border-color: var(--accent); } .cc-wrap .item.sel { border-color: var(--accent); background: var(--bg-hover); }
    .cc-wrap .cc-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: transparent; }
    .cc-wrap .b-prop { color: var(--warning); border: 1px solid var(--warning); }
    .cc-wrap .b-multi { color: var(--info); border: 1px solid var(--info); }
    .cc-wrap .b-esc { color: var(--danger); border: 1px solid var(--danger); }
    .cc-wrap .muted { color: var(--text-secondary); font-size: 12px; }
    .cc-wrap .cc-prev { min-height: 240px; display: flex; align-items: center; justify-content: center; background: var(--bg-secondary); border: 1px solid var(--border-default); border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
    .cc-wrap .cc-prev img { max-width: 100%; max-height: 60vh; } .cc-wrap .cc-prev iframe { width: 100%; height: 60vh; border: 0; }
    .cc-wrap table.cc-tbl { width: 100%; border-collapse: collapse; } .cc-wrap table.cc-tbl td, .cc-wrap table.cc-tbl th { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--border-default); font-size: 14px; color: var(--text-primary); }
    .cc-wrap table.cc-tbl th { color: var(--text-secondary); font-size: 12px; text-transform: uppercase; width: 38%; }
    .cc-wrap .cc-in { width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-default); background: var(--bg-primary); color: var(--text-primary); font-size: 14px; }
    .cc-wrap .cc-btn { border: 0; border-radius: 8px; padding: 9px 16px; cursor: pointer; font-size: 14px; font-weight: 600; }
    .cc-wrap .cc-ok { background: var(--success); color: #fff; } .cc-wrap .cc-no { background: var(--danger); color: #fff; } .cc-wrap .cc-ghost { background: var(--bg-hover); color: var(--text-primary); }
    .cc-wrap .cc-btn:disabled { opacity: .45; cursor: not-allowed; }
    .cc-wrap .row { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
    .cc-wrap .svc { display: block; padding: 7px 10px; border: 1px solid var(--border-default); border-radius: 8px; margin: 4px 0; cursor: pointer; font-size: 13px; background: var(--bg-primary); color: var(--text-primary); }
    .cc-wrap .svc.on { border-color: var(--accent); background: var(--bg-hover); }
    .cc-wrap .note { color: var(--text-secondary); font-size: 12px; margin-top: 8px; }
    .cc-wrap .warn { border: 1px solid var(--warning); border-left-width: 4px; color: var(--text-primary); background: var(--bg-hover); font-size: 12px; padding: 8px 10px; border-radius: 8px; margin: 10px 0; }
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
        <div class="tab" data-type="historial" onclick="ccSwitch('historial')">Historial</div>
        <div class="tab" data-type="verificacion" onclick="ccSwitch('verificacion')">Verificación bancaria <span class="n" id="cc-n-verificacion">{{ $counts['verificacion'] }}</span></div>
    </div>

    <div class="grid">
        <div class="cc-card cc-list" id="cc-list"><p class="muted">Cargando…</p></div>
        <div class="cc-card"><div id="cc-detail"><p class="muted">Selecciona un caso de la lista.</p></div></div>
    </div>

    <!-- Modal de resultado de la aplicación -->
    <div id="cc-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
        <div class="cc-card" style="max-width:440px; width:92%;">
            <div id="cc-modal-body"></div>
            <div class="row">
                <button class="cc-btn cc-ok" id="cc-modal-ficha" style="display:none;" onclick="ccGoFicha()">Ver ficha del cliente</button>
                <button class="cc-btn cc-ghost" onclick="ccCloseModal()">Cerrar y seguir</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Fuera de #init-vue (en @stack('scripts')) para NO romper el montaje de Vue del
     layout (topbar/versión + tema). Se carga en full-load (el enlace del sidebar
     usa data-spa-skip). --}}
<script>
(function(){
    const CSRF = "{{ csrf_token() }}";
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

    let aprobFrom = '', aprobTo = '', histEstado = 'todos';

    async function loadList(){
        const box = document.getElementById('cc-list'); if (!box) return;

        // VERIFICACIÓN BANCARIA — solo lectura (informativo por ahora).
        if (tab === 'verificacion'){
            const d = await get(U + '/list?type=verificacion');
            const banner = '<div class="warn">Solo lectura — verificación bancaria (cruce contra banco) pendiente de implementar.</div>';
            document.getElementById('cc-detail').innerHTML = '<p class="muted">Pestaña informativa (solo lectura).</p>';
            box.innerHTML = banner + (d.rows.length ? d.rows.map(r =>
                `<div class="item" style="cursor:default;"><b>$${esc(r.amount)}</b> · cliente ${esc(r.client_id)}<div class="muted">clave ${esc(r.clave_rastreo)} · ${esc(r.fecha_pago)}</div></div>`).join('')
                : '<p class="muted">Sin pagos pendientes de verificación bancaria.</p>');
            return;
        }

        // HISTORIAL — aprobados Y rechazados (solo lectura), filtro por tipo + fecha.
        if (tab === 'historial'){
            const qs = new URLSearchParams({type:'historial', estado: histEstado});
            if (aprobFrom) qs.set('from', aprobFrom);
            if (aprobTo) qs.set('to', aprobTo);
            const d = await get(U + '/list?' + qs.toString());
            const seg = (v,l) => `<button class="cc-btn ${histEstado===v?'cc-ok':'cc-ghost'}" style="padding:5px 12px;" onclick="ccHistEstado('${v}')">${l}</button>`;
            const filter = `<div class="row" style="margin:2px 0 8px;">
                    ${seg('todos','Todos')}${seg('aprobados','Aprobados')}${seg('rechazados','Rechazados')}
                </div>
                <div class="row" style="margin:0 0 10px;">
                    <span class="muted">del</span>
                    <input class="cc-in" style="width:auto;" type="date" value="${aprobFrom}" onchange="ccAprobFrom(this.value)">
                    <span class="muted">al</span>
                    <input class="cc-in" style="width:auto;" type="date" value="${aprobTo}" onchange="ccAprobTo(this.value)">
                    ${(aprobFrom||aprobTo)?'<button class="cc-btn cc-ghost" onclick="ccAprobClear()">Limpiar</button>':''}
                </div>`;
            document.getElementById('cc-detail').innerHTML = '<p class="muted">Historial de pagos por este flujo (solo lectura): aprobados y rechazados.</p>';
            const rows = d.rows.length ? d.rows.map(r => {
                let tag, extra;
                if (r.kind === 'aprobado'){
                    tag = r.auto
                        ? '<span class="cc-badge" style="color:var(--success);border:1px solid var(--success);">Aprobado · auto MEGAISP</span>'
                        : `<span class="cc-badge" style="color:var(--success);border:1px solid var(--success);">Aprobado · por ${esc(r.confirmed_by)}</span>`;
                    extra = `clave ${esc(r.clave_rastreo ?? '—')} · pago ${esc(r.fecha_pago ?? '—')} · aplicado ${esc(r.when ?? '')}`;
                } else {
                    tag = '<span class="cc-badge b-esc">Rechazado</span>';
                    extra = `motivo: ${esc(r.reject_reason ?? '—')} · por ${esc(r.rejected_by ?? '—')} · ${esc(r.when ?? '')}`;
                }
                const viewHint = r.has_media
                    ? '<div class="muted" style="margin-top:4px;">📎 Ver comprobante</div>'
                    : '<div class="muted" style="margin-top:4px;">Sin comprobante</div>';
                const clickAttr = r.has_media
                    ? `style="cursor:pointer;" onclick="ccHistView(${r.session_id}, '${esc(r.media_ext || '')}')"`
                    : 'style="cursor:default;"';
                return `<div class="item" ${clickAttr}>
                    <div>${tag} <b>$${esc(r.amount ?? '?')}</b></div>
                    <div style="margin-top:3px;">${r.client ? esc(r.client.name) : '<i>sin identificar</i>'}</div>
                    <div class="muted">${extra}</div>
                    ${viewHint}
                </div>`;
            }).join('') : '<p class="muted">Sin registros en el historial para este filtro.</p>';
            box.innerHTML = filter + rows;
            return;
        }

        // PROPUESTOS / ESCALADOS — accionables (clic abre detalle).
        const d = await get(U + '/list?type=' + tab);
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

        // Antifraude: duplicidad sospechosa de clave de rastreo (misma clave de varios números).
        let dupBlock = '';
        if (s.suspicious_duplicate && s.clave_sources && s.clave_sources.length){
            dupBlock = `<div class="warn" style="border-color:var(--danger);color:var(--danger);">
                ⚠️ <b>Duplicidad sospechosa</b>: esta clave de rastreo se recibió de varios números. Compárales antes de aplicar (posible reenvío de comprobante ajeno):
                <ul style="margin:6px 0 0 16px; padding:0;">
                ${s.clave_sources.map(x => `<li>${esc(x.phone||'—')} · ${esc(x.when||'')}</li>`).join('')}
                </ul></div>`;
        }

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
        </div>
        <div id="cc-reject-panel"></div>
        <div id="cc-actionRes" class="res"></div>
        <p class="note">Confirmar respeta el anti-duplicado de Fase 4. No se envía WhatsApp al cliente.</p>`;

        document.getElementById('cc-detail').innerHTML =
            `<div class="cc-prev">${media}</div>${dupBlock}
             <table class="cc-tbl">
                <tr><th>Monto</th><td><b>$${fld('monto')}</b></td></tr>
                <tr><th>Fecha del pago</th><td>${fld('fecha_pago')}${s.fecha_vieja ? ' <span class="cc-badge" style="color:var(--warning);border:1px solid var(--warning);">⚠️ fecha vieja</span>' : ''}</td></tr>
                <tr><th>Clave de rastreo</th><td>${fld('clave_rastreo')}</td></tr>
                <tr><th>Concepto</th><td>${fld('concepto')}</td></tr>
                <tr><th>Banco origen</th><td>${fld('banco_origen')}</td></tr>
                <tr><th>Titular</th><td>${fld('titular_ordenante')}</td></tr>
                <tr><th>Enviado desde</th><td>${s.sender_phone ? esc(s.sender_phone) : '<span class="muted">—</span>'} <span class="muted">· solo contexto, no identifica al cliente</span></td></tr>
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

    let modalFichaUrl = null;
    const REASON_TEXT = {
        duplicate_clave: 'Este comprobante ya fue aplicado (clave de rastreo duplicada).',
        already_applied: 'El caso ya había sido aplicado.',
        no_amount: 'El comprobante no tiene un monto legible.',
        no_clave: 'El comprobante no tiene clave de rastreo.',
        not_resolved: 'El caso no tiene cliente identificado.',
        auto_apply_disabled: 'La aplicación automática está deshabilitada.',
    };
    const fmtMoney = n => (n === null || n === undefined) ? '—' : Number(n).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});

    window.ccConfirm = async function(){
        if (!current) return;
        document.getElementById('cc-ok').disabled = true;
        const body = {};
        if (chosenService) body.service_id = chosenService;
        if (chosenClient) body.client_id = chosenClient;
        const {status, data} = await post(U + '/' + current + '/confirmar', body);
        if (status === 200 && data.applied){
            showModalSuccess(data);
            refreshCounts(); loadList();
            document.getElementById('cc-detail').innerHTML = '<p class="muted">Selecciona otro caso.</p>';
        } else {
            const reason = REASON_TEXT[data.reason] || data.reason || data.message || 'Error desconocido.';
            showModalError(reason);
            document.getElementById('cc-ok').disabled = false;
        }
    };

    function showModalSuccess(d){
        modalFichaUrl = d.ficha_url || null;
        document.getElementById('cc-modal-body').innerHTML =
            `<h1 style="color:var(--success); margin:0 0 4px;">✅ Pago aplicado con éxito</h1>
             <table class="cc-tbl" style="margin-top:8px;">
                <tr><th>Cliente</th><td>${esc(d.cliente || '—')}</td></tr>
                <tr><th>Monto aplicado</th><td><b>$${fmtMoney(d.monto)}</b></td></tr>
                <tr><th>Saldo anterior</th><td>$${fmtMoney(d.saldo_antes)}</td></tr>
                <tr><th>Saldo nuevo</th><td><b>$${fmtMoney(d.saldo_nuevo)}</b></td></tr>
                <tr><th>Fecha de corte</th><td>${esc(d.fecha_corte || '—')}</td></tr>
             </table>
             <p class="muted" style="margin-top:8px;">Aplicado como MEGAISP, confirmado por ti. No se notificó al cliente por WhatsApp.</p>`;
        document.getElementById('cc-modal-ficha').style.display = modalFichaUrl ? '' : 'none';
        document.getElementById('cc-modal').style.display = 'flex';
    }

    function showModalError(reason){
        modalFichaUrl = null;
        document.getElementById('cc-modal-body').innerHTML =
            `<h1 style="color:var(--danger); margin:0 0 4px;">No se pudo aplicar</h1>
             <p style="margin-top:6px;">Razón: <b>${esc(reason)}</b></p>
             <p class="muted" style="margin-top:8px;">El pago NO se aplicó. Revisa el caso.</p>`;
        document.getElementById('cc-modal-ficha').style.display = 'none';
        document.getElementById('cc-modal').style.display = 'flex';
    }

    window.ccCloseModal = function(){ document.getElementById('cc-modal').style.display = 'none'; };
    window.ccGoFicha = function(){ if (modalFichaUrl) window.location.href = modalFichaUrl; };

    const REJECT_REASONS = ['Duplicado', 'Comprobante ilegible', 'No es cliente', 'Monto no coincide', 'Otro'];

    window.ccReject = function(){
        if (!current) return;
        const opts = REJECT_REASONS.map(r => `<option value="${r}">${r}</option>`).join('');
        document.getElementById('cc-reject-panel').innerHTML =
            `<div class="warn" style="border-color:var(--danger);">
                <div style="margin-bottom:6px;">Motivo del rechazo (obligatorio):</div>
                <select class="cc-in" id="cc-rej-sel" style="margin-bottom:6px;">
                    <option value="">— elige un motivo —</option>${opts}
                </select>
                <input class="cc-in" id="cc-rej-note" placeholder="Nota adicional (obligatoria si el motivo es 'Otro')">
                <div class="row">
                    <button class="cc-btn cc-no" onclick="ccRejectSubmit()">Confirmar rechazo</button>
                    <button class="cc-btn cc-ghost" onclick="document.getElementById('cc-reject-panel').innerHTML=''">Cancelar</button>
                </div>
                <div id="cc-rej-err" class="res" style="color:var(--danger);"></div>
             </div>`;
    };

    window.ccRejectSubmit = async function(){
        const sel = document.getElementById('cc-rej-sel').value;
        const note = (document.getElementById('cc-rej-note').value || '').trim();
        const err = document.getElementById('cc-rej-err');
        if (!sel){ err.textContent = 'Elige un motivo.'; return; }
        if (sel === 'Otro' && !note){ err.textContent = 'Escribe la nota del motivo.'; return; }
        const reason = (sel === 'Otro') ? note : (note ? `${sel} — ${note}` : sel);
        const {status, data} = await post(U + '/' + current + '/rechazar', {reason});
        if (status === 200){
            refreshCounts(); loadList();
            document.getElementById('cc-detail').innerHTML = '<p class="muted">Caso rechazado (queda en Historial). Selecciona otro.</p>';
        } else {
            err.textContent = data.message || 'No se pudo rechazar.';
        }
    };

    window.ccAprobFrom = function(v){ aprobFrom = v; loadList(); };
    window.ccAprobTo = function(v){ aprobTo = v; loadList(); };
    window.ccAprobClear = function(){ aprobFrom = ''; aprobTo = ''; loadList(); };
    window.ccHistEstado = function(v){ histEstado = v; loadList(); };

    // Historial: ver el comprobante original (imagen/PDF) en el panel de detalle.
    // Reusa el endpoint protegido media/{session} (gateado por conciliacion.manage).
    window.ccHistView = function(sessionId, ext){
        const media = ext === 'pdf'
            ? `<iframe src="${U}/${sessionId}/media"></iframe>`
            : `<img src="${U}/${sessionId}/media">`;
        document.getElementById('cc-detail').innerHTML =
            `<h3 style="margin:0 0 8px;">Comprobante</h3><div class="cc-prev">${media}</div>
             <p class="muted">Evidencia original enviada por el cliente (solo lectura).</p>`;
    };

    async function refreshCounts(){
        for (const t of ['propuesto','escalado','verificacion']){
            const d = await get(U + '/list?type=' + t);
            const el = document.getElementById('cc-n-' + t); if (el) el.textContent = d.rows.length;
        }
    }

    // Arranca cuando el DOM esté listo (el script va en @stack('scripts'), full-load).
    if (document.getElementById('cc-list')) loadList();
    else document.addEventListener('DOMContentLoaded', loadList);
})();
</script>
@endpush
