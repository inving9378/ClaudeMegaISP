@extends('core-layout::master')
@section('title') Automatización de pagos (WhatsApp IA) @endsection

@section('styles')
<style>
    /* Scopeado bajo .ca-wrap + tokens de dark-light-tokens.css → sigue el tema. */
    .ca-wrap { padding: 6px 2px 40px; color: var(--text-primary); max-width: 820px; }
    .ca-wrap h1 { font-size: 20px; margin: 0 0 4px; color: var(--text-primary); }
    .ca-wrap .sub { color: var(--text-secondary); font-size: 13px; margin: 0 0 18px; }
    .ca-wrap .ca-card { background: var(--bg-surface); border: 1px solid var(--border-default); border-radius: 10px; padding: 16px 18px; box-shadow: var(--shadow-card); margin-bottom: 14px; }
    .ca-wrap .ca-row { display: flex; align-items: flex-start; gap: 16px; }
    .ca-wrap .ca-txt { flex: 1; }
    .ca-wrap .ca-label { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
    .ca-wrap .ca-help { color: var(--text-secondary); font-size: 13px; line-height: 1.45; margin: 0; }
    .ca-wrap .ca-state { font-size: 12px; font-weight: 700; margin-top: 8px; display: inline-block; padding: 1px 10px; border-radius: 999px; }
    .ca-wrap .ca-on  { color: var(--success); border: 1px solid var(--success); }
    .ca-wrap .ca-off { color: var(--text-secondary); border: 1px solid var(--border-default); }
    .ca-wrap .ca-danger-tag { color: var(--danger); border: 1px solid var(--danger); font-size: 11px; padding: 0 8px; border-radius: 999px; margin-left: 8px; }
    /* toggle */
    .ca-wrap .sw { position: relative; width: 52px; height: 30px; flex: 0 0 auto; cursor: pointer; }
    .ca-wrap .sw input { opacity: 0; width: 0; height: 0; }
    .ca-wrap .sw .track { position: absolute; inset: 0; background: var(--bg-hover); border: 1px solid var(--border-default); border-radius: 999px; transition: .2s; }
    .ca-wrap .sw .knob { position: absolute; top: 3px; left: 3px; width: 22px; height: 22px; background: #fff; border-radius: 50%; transition: .2s; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
    .ca-wrap .sw input:checked + .track { background: var(--success); border-color: var(--success); }
    .ca-wrap .sw input:checked + .track + .knob { transform: translateX(22px); }
    .ca-wrap .sw.busy { opacity: .5; pointer-events: none; }
    .ca-wrap .sw.disabled { opacity: .35; pointer-events: none; }
    .ca-wrap .banner { border: 1px solid var(--warning); border-left-width: 4px; background: var(--bg-hover); color: var(--text-primary); font-size: 13px; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; }
    .ca-wrap .src { color: var(--text-secondary); font-size: 11px; margin-top: 6px; }
    /* maestro destacado */
    .ca-wrap .ca-master { border: 2px solid var(--accent); background: var(--bg-hover); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent); }
    .ca-wrap .ca-master-tag { color: var(--accent); border: 1px solid var(--accent); font-size: 11px; font-weight: 700; padding: 0 8px; border-radius: 999px; margin-left: 8px; text-transform: uppercase; letter-spacing: .3px; }
    /* dependiente atenuado cuando el maestro está OFF */
    .ca-wrap .ca-dim { opacity: .5; }
    .ca-wrap .ca-note-off { color: var(--warning); font-size: 12px; font-weight: 600; margin-top: 6px; }
    /* separador visual entre el maestro y los dependientes */
    .ca-wrap .ca-master { margin-bottom: 20px; }
</style>
@endsection

@section('content')
<div class="ca-wrap">
    <h1>Automatización de pagos (WhatsApp IA)</h1>
    <p class="sub">Interruptores del asistente de conciliación por WhatsApp. Cambiarlos tiene efecto inmediato en el sistema (no requiere tocar el servidor). Sólo el super-administrador ve y controla esta pantalla.</p>

    <div class="banner">⚠️ El <b>interruptor general</b> (arriba) activa todo el sistema. Los 3 de abajo solo tienen efecto si el general está encendido. Durante la semana de observación, enciende de uno en uno vigilando el comportamiento; deja apagados los que aplican dinero automáticamente.</div>

    <div id="ca-list"></div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const CSRF = "{{ csrf_token() }}";
    const U = "{{ url('finanzas/conciliacion-config') }}";
    const SWITCHES = @json($switches);

    const esc = s => (s==null?'':String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

    function render(list){
        // El maestro (master) manda: si está OFF, los dependientes se atenúan y
        // se deshabilitan (no tienen efecto aunque estén encendidos).
        const master = list.find(s => s.master);
        const masterOn = master ? !!master.enabled : true;

        const card = (sw) => {
            const on = !!sw.enabled;
            const isMaster = !!sw.master;
            const disabled = !isMaster && !masterOn;   // dependiente con maestro OFF
            const danger = sw.danger ? '<span class="ca-danger-tag">mueve dinero</span>' : '';
            const masterTag = isMaster ? '<span class="ca-master-tag">interruptor general</span>' : '';
            const state = on
                ? '<span class="ca-state ca-on">ENCENDIDO</span>'
                : '<span class="ca-state ca-off">APAGADO</span>';
            const note = disabled
                ? '<div class="ca-note-off">Sin efecto mientras el interruptor general esté apagado.</div>' : '';
            return `<div class="ca-card ${isMaster?'ca-master':''} ${disabled?'ca-dim':''}">
                <div class="ca-row">
                    <div class="ca-txt">
                        <p class="ca-label">${esc(sw.label)}${masterTag}${danger}</p>
                        <p class="ca-help">${esc(sw.help)}</p>
                        ${state}
                        <div class="src">Estado leído de: ${sw.source === 'db' ? 'configuración del sistema' : 'valor por defecto (.env)'}</div>
                        ${note}
                    </div>
                    <label class="sw ${disabled?'disabled':''}" data-key="${esc(sw.key)}" data-danger="${sw.danger?1:0}">
                        <input type="checkbox" ${on?'checked':''} ${disabled?'disabled':''}>
                        <span class="track"></span><span class="knob"></span>
                    </label>
                </div>
            </div>`;
        };

        // Maestro primero, luego los dependientes (en el orden de KEYS).
        const ordered = [...list].sort((a,b) => (b.master?1:0) - (a.master?1:0));
        document.getElementById('ca-list').innerHTML = ordered.map(card).join('');
        bind();
    }

    // Aplica/quita el atenuado+deshabilitado de los 3 dependientes según el
    // estado del maestro, SIN re-render — reactividad instantánea al mover el
    // interruptor general (no espera al servidor).
    function applyMasterGate(masterOn){
        // Ops de DOM universalmente seguras (add/remove/removeChild) — se evita
        // classList.toggle(force) y Node.remove() para no lanzar en navegadores
        // viejos; además la llamada va envuelta en try/catch (ver handler).
        const cards = document.querySelectorAll('.ca-wrap .ca-card');
        for (let i = 0; i < cards.length; i++) {
            const cardEl = cards[i];
            const lbl = cardEl.querySelector('.sw');
            if (!lbl || lbl.dataset.key === 'wa_conciliation') continue; // el maestro no se atenúa
            const input = lbl.querySelector('input');
            const off = !masterOn;
            if (off) { cardEl.classList.add('ca-dim'); lbl.classList.add('disabled'); }
            else     { cardEl.classList.remove('ca-dim'); lbl.classList.remove('disabled'); }
            if (input) input.disabled = off;
            const txt = cardEl.querySelector('.ca-txt');
            let note = cardEl.querySelector('.ca-note-off');
            if (off && !note && txt) {
                note = document.createElement('div');
                note.className = 'ca-note-off';
                note.textContent = 'Sin efecto mientras el interruptor general esté apagado.';
                txt.appendChild(note);
            } else if (!off && note && note.parentNode) {
                note.parentNode.removeChild(note);
            }
        }
    }

    function bind(){
        document.querySelectorAll('.ca-wrap .sw').forEach(lbl => {
            const input = lbl.querySelector('input');
            input.addEventListener('change', async (e) => {
                const key = lbl.dataset.key;
                const danger = lbl.dataset.danger === '1';
                const want = input.checked;

                // Confirmación antes de ENCENDER un interruptor que mueve dinero.
                if (want && danger) {
                    const ok = window.confirm('Esto hará que la IA mueva saldo de clientes automáticamente. ¿Continuar?');
                    if (!ok) { input.checked = false; return; }
                }

                // Reactividad INSTANTÁNEA: si es el maestro, atenúa/rehabilita los
                // dependientes ya mismo (optimista), sin esperar al servidor.
                // try/catch: un fallo en el gate visual NUNCA debe impedir el
                // guardado (POST) — el guardado es lo crítico.
                const isMaster = key === 'wa_conciliation';
                if (isMaster) { try { applyMasterGate(want); } catch (e) { console.warn('gate visual falló (se guarda igual):', e); } }

                lbl.classList.add('busy');
                try {
                    const r = await fetch(U, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify({key, enabled: want})
                    });
                    const data = await r.json().catch(()=>({}));
                    if (!r.ok || !data.ok) {
                        input.checked = !want;              // revertir en error
                        if (isMaster) applyMasterGate(!want); // revertir el gate también
                        alert('No se pudo guardar el cambio' + (r.status===403?' (sin permiso).':'.'));
                        lbl.classList.remove('busy');
                        return;
                    }
                    render(data.switches); // re-render con estado fresco del servidor (autoritativo)
                } catch (err) {
                    input.checked = !want;
                    if (isMaster) applyMasterGate(!want); // revertir el gate
                    alert('Error de red al guardar el cambio.');
                    lbl.classList.remove('busy');
                }
            });
        });
    }

    // Arranca cuando el DOM esté listo (el script va en @stack('scripts'), full-load;
    // la ruta está en SPA_BLACKLIST para forzar recarga completa y que esto corra).
    if (document.getElementById('ca-list')) render(SWITCHES);
    else document.addEventListener('DOMContentLoaded', () => render(SWITCHES));
})();
</script>
@endpush
