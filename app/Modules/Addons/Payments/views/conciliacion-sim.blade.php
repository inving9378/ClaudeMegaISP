<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Simulador de identificación de pago (F3)</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 20px 16px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin: 0 0 6px; }
        .crit { color: #f59e0b; font-size: 12px; margin: 0 0 16px; }
        .grid { display: grid; grid-template-columns: 360px 1fr; gap: 18px; align-items: start; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 16px; }
        label { display: block; font-size: 13px; margin: 12px 0 5px; color: #cbd5e1; }
        input, select, textarea { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #e2e8f0; font-size: 14px; }
        button { background: #06b6d4; color: #04222b; font-weight: 600; border: 0; border-radius: 8px; padding: 9px 16px; cursor: pointer; font-size: 14px; }
        button.ghost { background: #334155; color: #e2e8f0; }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .row { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .transcript { min-height: 300px; max-height: 60vh; overflow: auto; display: flex; flex-direction: column; gap: 10px; padding: 4px; }
        .bubble { max-width: 78%; padding: 9px 12px; border-radius: 12px; font-size: 14px; white-space: pre-wrap; line-height: 1.35; }
        .bot { align-self: flex-start; background: #0b3a44; border: 1px solid #0e7490; }
        .client { align-self: flex-end; background: #1e3a5f; border: 1px solid #2563eb; }
        .note { align-self: center; color: #94a3b8; font-size: 12px; font-style: italic; }
        .status { margin-top: 14px; padding: 10px 12px; background: #0b1220; border: 1px solid #334155; border-radius: 8px; font-size: 13px; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; margin-right: 6px; }
        .b-exact { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .b-proposed { background: #422006; color: #fbbf24; border: 1px solid #a16207; }
        .b-state { background: #1e293b; color: #cbd5e1; border: 1px solid #475569; }
        .b-esc { background: #450a0a; color: #f87171; border: 1px solid #991b1b; }
        .muted { color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Simulador de identificación de pago</h1>
    <p class="sub">Juega la conversación paso a paso. Corre el mismo motor que producción.</p>
    <p class="crit">⚠️ SIMULACIÓN: no se envía ningún mensaje real por WhatsApp. No aplica pago, no identifica en real. Las sesiones quedan marcadas como simulación.</p>

    <div class="grid">
        <div class="card">
            <b style="font-size:14px;">1. Arma el escenario</b>
            <label>Comprobante existente (opcional)</label>
            <select id="extraction" onchange="prefill()">
                <option value="">— escribir concepto manual —</option>
                @foreach ($extractions as $e)
                    <option value="{{ $e['id'] }}" data-concepto="{{ $e['concepto'] }}" data-titular="{{ $e['titular'] }}">{{ $e['label'] }}</option>
                @endforeach
            </select>
            <label>Concepto (lo que escribió el pagador)</label>
            <input id="concepto" placeholder="ej. MEG-00000017-47  ó  MARIA DE LOS ANGELES CRUZ LOPEZ">
            <label>Titular ordenante (opcional)</label>
            <input id="titular" placeholder="dueño de la cuenta que envía">
            <label>Teléfono del remitente (opcional, solo desempata)</label>
            <input id="phone" placeholder="10 dígitos">
            <div class="row">
                <button onclick="startSim()">Iniciar simulación</button>
            </div>
            <p class="muted" style="margin-top:14px;">Escenarios sugeridos: una ref MEG en el concepto (identifica exacto), un nombre real (propuesta), un nombre repetido (desambigua), y basura (reintenta y escala).</p>
        </div>

        <div class="card">
            <b style="font-size:14px;">2. La conversación</b>
            <div id="transcript" class="transcript"><p class="note">Inicia una simulación para empezar.</p></div>

            <div id="status" class="status" style="display:none;"></div>

            <label>Responder como cliente</label>
            <div class="row">
                <input id="reply" placeholder="escribe lo que respondería el cliente…" disabled onkeydown="if(event.key==='Enter')sendReply()">
                <button id="btnSend" onclick="sendReply()" disabled>Enviar</button>
            </div>
            <label>Acelerar el tiempo (sin esperar horas) — reloj de silencio: <span id="clock">0 min</span></label>
            <div class="row">
                <button class="ghost" id="btnAdv1" onclick="advance(60)" disabled>Avanzar 1h</button>
                <button class="ghost" id="btnAdv5" onclick="advance(300)" disabled>Avanzar 5h</button>
                <button class="ghost" id="btnExpire" onclick="advance('expire')" disabled>Expirar</button>
                <button class="ghost" id="btnReset" onclick="resetSim()" disabled>Reiniciar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const U = "{{ url('finanzas/conciliacion-sim') }}";
    let sid = null;
    let elapsed = 0; // minutos de silencio acumulados (simulado)

    function setClock(txt) { document.getElementById('clock').textContent = txt; }

    function prefill() {
        const opt = document.getElementById('extraction').selectedOptions[0];
        document.getElementById('concepto').value = opt.dataset.concepto || '';
        document.getElementById('titular').value = opt.dataset.titular || '';
    }

    async function post(path, body) {
        const r = await fetch(U + path, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body || {}),
        });
        return r.json();
    }

    function esc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

    function add(kind, text) {
        const t = document.getElementById('transcript');
        const d = document.createElement('div');
        d.className = kind === 'client' ? 'bubble client' : (kind === 'bot' ? 'bubble bot' : 'note');
        d.textContent = text;
        t.appendChild(d);
        t.scrollTop = t.scrollHeight;
    }

    function renderStep(step) {
        if (step.outbound) add('bot', step.outbound);
        else if (step.reminder_skipped) add('note', '(recordatorio omitido: no aplica en este estado)');
        else if (step.method === 'meg') add('note', '✓ Identificado por referencia MEG — sin mensaje al cliente (F4 confirmará).');

        // Status
        const s = document.getElementById('status');
        s.style.display = 'block';
        let cert = step.certainty
            ? `<span class="badge ${step.certainty === 'exact' ? 'b-exact' : 'b-proposed'}">${step.certainty === 'exact' ? 'EXACT (auto-aplicable)' : 'PROPOSED (confirma humano)'}</span>`
            : '';
        let stateBadge = `<span class="badge ${step.session_state === 'escalated' ? 'b-esc' : 'b-state'}">${esc(step.session_state || step.state)}</span>`;
        let who = step.resolved_client_name ? ` · Cliente: <b>${esc(step.resolved_client_name)}</b> (id ${step.resolved_client_id})` : '';
        let method = step.method ? ` · método: ${esc(step.method)}` : '';
        let att = (step.attempts !== undefined) ? ` · intentos: ${step.attempts}/2` : '';
        let esc2 = step.reason ? ` · escaló: ${esc(step.reason)}` : '';
        s.innerHTML = stateBadge + cert + who + method + att + esc2;

        const terminal = !!step.terminal;
        ['reply','btnSend','btnAdv1','btnAdv5','btnExpire'].forEach(id => document.getElementById(id).disabled = terminal);
        if (terminal) add('note', '— fin de la conversación —');
    }

    async function startSim() {
        const body = {
            concepto: document.getElementById('concepto').value,
            titular: document.getElementById('titular').value,
            phone: document.getElementById('phone').value,
            extraction_id: document.getElementById('extraction').value || null,
        };
        const data = await post('/iniciar', body);
        sid = data.session_id;
        elapsed = 0; setClock('0 min');
        document.getElementById('transcript').innerHTML = '';
        add('note', `Comprobante · concepto: "${data.input.concepto || '(vacío)'}"` + (data.input.titular ? ` · titular: "${data.input.titular}"` : ''));
        document.getElementById('btnReset').disabled = false;
        renderStep(data.step);
    }

    async function sendReply() {
        if (!sid) return;
        const el = document.getElementById('reply');
        const txt = el.value.trim();
        if (!txt) return;
        add('client', txt);
        el.value = '';
        elapsed = 0; setClock('0 min'); // respuesta del cliente reinicia el reloj
        const data = await post('/responder', { session_id: sid, text: txt });
        renderStep(data.step);
    }

    async function advance(delta) {
        if (!sid) return;
        let minutes;
        if (delta === 'expire') { minutes = 100000; setClock('expirada'); add('note', '⏱️ Simulando expiración de la sesión…'); }
        else { elapsed += delta; minutes = elapsed; setClock(elapsed + ' min (' + (elapsed / 60).toFixed(1) + ' h)'); add('note', '⏱️ Pasó el tiempo: ' + (elapsed / 60).toFixed(1) + ' h de silencio'); }
        const data = await post('/avanzar', { session_id: sid, minutes });
        renderStep(data.step);
    }

    async function resetSim() {
        if (sid) await post('/reiniciar', { session_id: sid });
        sid = null;
        elapsed = 0; setClock('0 min');
        document.getElementById('transcript').innerHTML = '<p class="note">Inicia una simulación para empezar.</p>';
        document.getElementById('status').style.display = 'none';
        ['reply','btnSend','btnAdv1','btnAdv5','btnExpire','btnReset'].forEach(id => document.getElementById(id).disabled = true);
    }
</script>
</body>
</html>
