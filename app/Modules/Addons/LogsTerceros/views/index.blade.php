@extends('core-layout::master')

@section('title')
    Logs de terceros
@endsection

@section('content')
<div class="py-3" id="logs-terceros"
     data-url-datos="{{ route('logs-terceros.datos') }}"
     data-url-base="{{ url('/admin/logs-terceros') }}"
     data-gestionar="{{ $puedeGestionar ? '1' : '0' }}">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>Logs de terceros</h4>
            <div class="text-muted small">Logs del servidor agrupados, completos y para copiar. Solo lectura: aquí no se edita ni se borra ningún archivo.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="logsTerceros.cargar()">
                <i class="fas fa-sync-alt me-1"></i>Actualizar lista
            </button>
            <button type="button" id="lt-nuevo-grupo" class="btn btn-sm btn-primary d-none" onclick="logsTerceros.formGrupo()">
                <i class="fas fa-folder-plus me-1"></i>Nuevo grupo
            </button>
        </div>
    </div>

    <div id="lt-aviso" class="alert d-none" role="alert"></div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div id="lt-grupos"><div class="text-muted small">Cargando…</div></div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body pb-2">
                    <div id="lt-vacio" class="text-muted small py-4 text-center">Elige un log de la izquierda para verlo.</div>
                    <div id="lt-visor" class="d-none">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div class="me-2" style="min-width:0">
                                <div class="fw-semibold" id="lt-titulo"></div>
                                <div class="small text-muted text-break" id="lt-ruta" style="font-family:monospace"></div>
                                <div class="small text-muted" id="lt-meta"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="logsTerceros.recargar()" title="Volver a leer lo último"><i class="fas fa-sync-alt"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="lt-seguir" onclick="logsTerceros.seguir()" title="Ir agregando lo nuevo cada 3 segundos"><i class="fas fa-play me-1"></i>Seguir</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="lt-antiguas" onclick="logsTerceros.antiguas()"><i class="fas fa-arrow-up me-1"></i>Más antiguas</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="lt-todo" onclick="logsTerceros.todo()"><i class="fas fa-expand me-1"></i>Ver todo</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="logsTerceros.copiar()"><i class="fas fa-copy me-1"></i>Copiar</button>
                                <a class="btn btn-sm btn-outline-primary" id="lt-descargar" href="#" data-spa-skip><i class="fas fa-download me-1"></i>Descargar</a>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <input type="search" id="lt-filtro" class="form-control form-control-sm" style="max-width:320px"
                                   placeholder="Filtrar líneas (no distingue mayúsculas)" oninput="logsTerceros.filtrar()">
                            <div class="form-check form-check-inline small mb-0">
                                <input class="form-check-input" type="checkbox" id="lt-ajustar" onchange="logsTerceros.ajustar()">
                                <label class="form-check-label" for="lt-ajustar">Ajustar líneas largas</label>
                            </div>
                            <span class="small text-muted ms-auto" id="lt-cuenta"></span>
                        </div>
                        <pre id="lt-texto" class="mb-0 p-2 rounded" style="background:#111;color:#ddd;font-size:12px;line-height:1.35;height:calc(100vh - 330px);min-height:320px;overflow:auto;white-space:pre;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grupo --}}
    <div class="modal fade" id="lt-modal-grupo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="lt-grupo-titulo">Nuevo grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="lt-grupo-id">
                <label class="form-label small" for="lt-grupo-nombre">Nombre</label>
                <input type="text" class="form-control mb-2" id="lt-grupo-nombre" maxlength="120" placeholder="Ej. Central telefónica">
                <label class="form-label small" for="lt-grupo-desc">Descripción (opcional)</label>
                <textarea class="form-control" id="lt-grupo-desc" rows="2" maxlength="500"></textarea>
                <div class="text-danger small mt-2" id="lt-grupo-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="logsTerceros.guardarGrupo()">Guardar</button>
            </div>
        </div></div>
    </div>

    {{-- Archivo --}}
    <div class="modal fade" id="lt-modal-archivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="lt-archivo-titulo">Agregar log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="lt-archivo-id">
                <label class="form-label small" for="lt-archivo-grupo">Grupo</label>
                <select class="form-select mb-2" id="lt-archivo-grupo"></select>
                <label class="form-label small" for="lt-archivo-nombre">Nombre</label>
                <input type="text" class="form-control mb-2" id="lt-archivo-nombre" maxlength="120" placeholder="Ej. Asterisk — mensajes">
                <label class="form-label small" for="lt-archivo-ruta">Ruta absoluta (desde /)</label>
                <input type="text" class="form-control" id="lt-archivo-ruta" maxlength="1024" list="lt-sugerencias"
                       style="font-family:monospace" placeholder="/var/log/asterisk/messages.log" oninput="logsTerceros.sugerir()">
                <datalist id="lt-sugerencias"></datalist>
                <div class="form-text">Solo logs: bajo /var/log, con «log» en la ruta o terminados en .out/.err. No se aceptan .env, llaves ni /proc.
                    Para los que cambian de nombre cada día usa <code>*</code> y se verá el más reciente, p. ej. <code>…/storage/logs/laravel-*.log</code>.</div>
                <div class="text-danger small mt-2" id="lt-archivo-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="logsTerceros.guardarArchivo()">Guardar</button>
            </div>
        </div></div>
    </div>
</div>
@endsection

@push('scripts-spa')
<script>
window.logsTerceros = (function () {
    let grupos = [], sugerencias = [], gestionar = false;
    let actual = null;                         // log abierto
    let texto = '', desde = 0, hasta = 0, tamano = 0, inicio = false;
    let reloj = null;

    const $ = (id) => document.getElementById(id);
    const raiz = () => $('logs-terceros');
    const base = () => raiz().dataset.urlBase;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fecha = (seg) => seg ? new Date(seg * 1000).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'medium' }) : '';
    const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));

    function aviso(msg, tipo) {
        const el = $('lt-aviso');
        if (!el) return;
        el.className = 'alert alert-' + (tipo || 'info') + (msg ? '' : ' d-none');
        el.textContent = msg || '';
    }

    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }, opciones || {}));
        const datos = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(datos.error || datos.message || ('Error ' + r.status));
        return datos;
    }

    // ── Lista de grupos ─────────────────────────────────────────────
    async function cargar() {
        if (!raiz()) return;
        try {
            const d = await pedir(raiz().dataset.urlDatos);
            grupos = d.grupos; sugerencias = d.sugerencias; gestionar = d.puede_gestionar;
            pintarGrupos();
        } catch (e) { aviso(e.message, 'danger'); }
    }

    function pintarGrupos() {
        $('lt-nuevo-grupo')?.classList.toggle('d-none', !gestionar);
        const cont = $('lt-grupos');
        if (!cont) return;
        if (!grupos.length) {
            cont.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body small text-muted">'
                + (gestionar ? 'Todavía no hay grupos. Crea uno con «Nuevo grupo» y agrégale la ruta de sus logs.' : 'Todavía no hay logs registrados.')
                + '</div></div>';
            return;
        }
        cont.innerHTML = grupos.map((g) => `
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width:0"><div class="fw-semibold">${esc(g.nombre)}</div>
                        ${g.descripcion ? `<div class="small text-muted">${esc(g.descripcion)}</div>` : ''}</div>
                    ${gestionar ? `<div class="btn-group btn-group-sm flex-shrink-0">
                        <button type="button" class="btn btn-outline-primary" title="Agregar log" onclick="logsTerceros.formArchivo(null, ${g.id})"><i class="fas fa-plus"></i></button>
                        <button type="button" class="btn btn-outline-secondary" title="Cambiar grupo" onclick="logsTerceros.formGrupo(${g.id})"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-outline-danger" title="Quitar grupo" onclick="logsTerceros.borrarGrupo(${g.id})"><i class="fas fa-trash"></i></button>
                    </div>` : ''}
                </div>
                <div class="list-group list-group-flush">
                    ${g.archivos.length ? g.archivos.map((a) => `
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2 ${actual && actual.id === a.id ? 'active' : ''}"
                             style="cursor:pointer" onclick="logsTerceros.abrir(${a.id})">
                            <div style="min-width:0">
                                <div class="small fw-semibold">${esc(a.nombre)}</div>
                                <div class="small text-break opacity-75" style="font-family:monospace;font-size:11px">${esc(a.ruta)}${a.actual ? ' → ' + esc(a.actual.split('/').pop()) : ''}</div>
                                <div class="small ${a.legible ? 'opacity-75' : 'text-danger'}">${a.legible ? esc((a.tamano_txt || '') + ' · ' + fecha(a.modificado)) : '<i class="fas fa-exclamation-triangle me-1"></i>' + esc(a.motivo)}</div>
                            </div>
                            ${gestionar ? `<div class="btn-group btn-group-sm flex-shrink-0" onclick="event.stopPropagation()">
                                <button type="button" class="btn btn-sm btn-link p-0 me-2 ${actual && actual.id === a.id ? 'text-white' : ''}" title="Cambiar" onclick="logsTerceros.formArchivo(${a.id})"><i class="fas fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-link p-0 ${actual && actual.id === a.id ? 'text-white' : 'text-danger'}" title="Quitar de la lista" onclick="logsTerceros.borrarArchivo(${a.id})"><i class="fas fa-times"></i></button>
                            </div>` : ''}
                        </div>`).join('') : '<div class="list-group-item small text-muted">Sin logs todavía.</div>'}
                </div>
            </div>`).join('');
    }

    const buscarArchivo = (id) => grupos.flatMap((g) => g.archivos).find((a) => a.id === id);

    // ── Visor ───────────────────────────────────────────────────────
    function urlContenido(params) {
        const q = new URLSearchParams(params).toString();
        return `${base()}/archivos/${actual.id}/contenido${q ? '?' + q : ''}`;
    }

    function pintarTexto(alFinal) {
        const pre = $('lt-texto');
        if (!pre) return;
        const filtro = ($('lt-filtro')?.value || '').trim().toLowerCase();
        const lineas = texto.split('\n');
        if (lineas.length && lineas[lineas.length - 1] === '') lineas.pop();
        const vistas = filtro ? lineas.filter((l) => l.toLowerCase().includes(filtro)) : lineas;
        pre.textContent = vistas.join('\n');
        $('lt-cuenta').textContent = (filtro ? `${vistas.length.toLocaleString('es-MX')} de ` : '') + `${lineas.length.toLocaleString('es-MX')} líneas`
            + (inicio ? ' · desde el principio' : ' · faltan las más antiguas');
        $('lt-antiguas').disabled = inicio;
        $('lt-todo').disabled = inicio && hasta >= tamano;
        if (alFinal) pre.scrollTop = pre.scrollHeight;
    }

    function pintarMeta(r) {
        $('lt-meta').textContent = (r.actual ? `Ahora: ${r.actual} · ` : '') + `${r.tamano_txt || ''} · modificado ${fecha(r.modificado)}`;
    }

    async function abrir(id) {
        detenerSeguir();
        actual = buscarArchivo(id);
        if (!actual) return;
        $('lt-vacio').classList.add('d-none');
        $('lt-visor').classList.remove('d-none');
        $('lt-titulo').textContent = actual.nombre;
        $('lt-ruta').textContent = actual.ruta;
        $('lt-descargar').href = `${base()}/archivos/${actual.id}/descargar`;
        $('lt-filtro').value = '';
        pintarGrupos();
        await recargar();
    }

    async function recargar() {
        if (!actual) return;
        aviso('');
        try {
            const r = await pedir(urlContenido({}));
            texto = r.texto; desde = r.desde; hasta = r.hasta; tamano = r.tamano; inicio = r.inicio;
            pintarMeta(r); pintarTexto(true);
        } catch (e) {
            texto = ''; pintarTexto(false);
            $('lt-meta').textContent = '';
            aviso(e.message, 'warning');
        }
    }

    async function antiguas() {
        if (!actual || inicio) return;
        const pre = $('lt-texto');
        const altoAntes = pre.scrollHeight;
        try {
            const r = await pedir(urlContenido({ hasta: desde }));
            texto = r.texto + texto; desde = r.desde; inicio = r.inicio;
            pintarTexto(false);
            pre.scrollTop = pre.scrollHeight - altoAntes;
        } catch (e) { aviso(e.message, 'warning'); }
    }

    async function todo() {
        if (!actual) return;
        try {
            const r = await pedir(urlContenido({ completo: 1 }));
            texto = r.texto; desde = r.desde; hasta = r.hasta; tamano = r.tamano; inicio = r.inicio;
            pintarMeta(r); pintarTexto(true);
        } catch (e) { aviso(e.message, 'warning'); }
    }

    function detenerSeguir() {
        clearInterval(reloj); reloj = null;
        const b = $('lt-seguir');
        if (b) { b.classList.remove('btn-success'); b.classList.add('btn-outline-secondary'); b.innerHTML = '<i class="fas fa-play me-1"></i>Seguir'; }
    }

    function seguir() {
        if (reloj) { detenerSeguir(); return; }
        if (!actual) return;
        const b = $('lt-seguir');
        b.classList.remove('btn-outline-secondary'); b.classList.add('btn-success'); b.innerHTML = '<i class="fas fa-pause me-1"></i>Siguiendo';
        const id = actual.id;
        reloj = setInterval(async () => {
            if (!raiz() || !actual || actual.id !== id) { detenerSeguir(); return; }
            try {
                const r = await pedir(urlContenido({ despues: hasta }));
                if (r.desde !== hasta) { texto = ''; inicio = false; desde = r.desde; }   // se rotó
                hasta = r.hasta; tamano = r.tamano; pintarMeta(r);
                if (r.texto) {
                    const pre = $('lt-texto');
                    const abajo = pre.scrollTop + pre.clientHeight >= pre.scrollHeight - 30;
                    texto += r.texto;
                    pintarTexto(abajo);
                }
            } catch (e) { detenerSeguir(); aviso(e.message, 'warning'); }
        }, 3000);
    }

    let filtroEspera = null;
    function filtrar() { clearTimeout(filtroEspera); filtroEspera = setTimeout(() => pintarTexto(false), 250); }
    function ajustar() { $('lt-texto').style.whiteSpace = $('lt-ajustar').checked ? 'pre-wrap' : 'pre'; }

    async function copiar() {
        const t = $('lt-texto')?.textContent || '';
        try {
            await navigator.clipboard.writeText(t);
        } catch (e) {
            // http sin TLS: navigator.clipboard no existe
            const area = document.createElement('textarea');
            area.value = t; area.style.position = 'fixed'; area.style.opacity = '0';
            document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove();
        }
        aviso(`Copiadas ${t ? t.split('\n').length.toLocaleString('es-MX') : 0} líneas.`, 'success');
        setTimeout(() => aviso(''), 2500);
    }

    // ── Grupos y archivos (alta, cambio y baja de la LISTA; los archivos no se tocan) ──
    function formGrupo(id) {
        const g = id ? grupos.find((x) => x.id === id) : null;
        $('lt-grupo-titulo').textContent = g ? 'Cambiar grupo' : 'Nuevo grupo';
        $('lt-grupo-id').value = g ? g.id : '';
        $('lt-grupo-nombre').value = g ? g.nombre : '';
        $('lt-grupo-desc').value = g ? (g.descripcion || '') : '';
        $('lt-grupo-error').textContent = '';
        modal('lt-modal-grupo').show();
        setTimeout(() => $('lt-grupo-nombre').focus(), 300);
    }

    async function guardarGrupo() {
        const id = $('lt-grupo-id').value;
        try {
            await pedir(id ? `${base()}/grupos/${id}` : `${base()}/grupos`, {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify({ nombre: $('lt-grupo-nombre').value, descripcion: $('lt-grupo-desc').value }),
            });
            modal('lt-modal-grupo').hide();
            await cargar();
        } catch (e) { $('lt-grupo-error').textContent = e.message; }
    }

    async function borrarGrupo(id) {
        const g = grupos.find((x) => x.id === id);
        if (!g || !confirm(`¿Quitar el grupo «${g.nombre}» y sus ${g.archivos.length} logs de la lista?\nLos archivos del servidor no se tocan.`)) return;
        try {
            await pedir(`${base()}/grupos/${id}`, { method: 'DELETE' });
            if (actual && g.archivos.some((a) => a.id === actual.id)) cerrarVisor();
            await cargar();
        } catch (e) { aviso(e.message, 'danger'); }
    }

    function formArchivo(id, grupoId) {
        const a = id ? buscarArchivo(id) : null;
        $('lt-archivo-titulo').textContent = a ? 'Cambiar log' : 'Agregar log';
        $('lt-archivo-id').value = a ? a.id : '';
        $('lt-archivo-grupo').innerHTML = grupos.map((g) => `<option value="${g.id}">${esc(g.nombre)}</option>`).join('');
        $('lt-archivo-grupo').value = a ? a.grupo_id : (grupoId || (grupos[0] && grupos[0].id));
        $('lt-archivo-nombre').value = a ? a.nombre : '';
        $('lt-archivo-ruta').value = a ? a.ruta : '';
        $('lt-sugerencias').innerHTML = sugerencias.map((s) => `<option value="${esc(s.ruta)}">${esc(s.nombre)}${s.legible ? '' : ' (sin permiso de lectura)'}</option>`).join('');
        $('lt-archivo-error').textContent = '';
        modal('lt-modal-archivo').show();
        setTimeout(() => $('lt-archivo-' + (a ? 'nombre' : 'ruta')).focus(), 300);
    }

    // Al elegir una sugerencia, si no hay nombre se pone el de la sugerencia.
    function sugerir() {
        const s = sugerencias.find((x) => x.ruta === $('lt-archivo-ruta').value);
        if (s && !$('lt-archivo-nombre').value.trim()) $('lt-archivo-nombre').value = s.nombre;
    }

    async function guardarArchivo() {
        const id = $('lt-archivo-id').value;
        try {
            await pedir(id ? `${base()}/archivos/${id}` : `${base()}/archivos`, {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify({ grupo_id: $('lt-archivo-grupo').value, nombre: $('lt-archivo-nombre').value, ruta: $('lt-archivo-ruta').value }),
            });
            modal('lt-modal-archivo').hide();
            await cargar();
            if (actual && String(actual.id) === String(id)) await abrir(actual.id);
        } catch (e) { $('lt-archivo-error').textContent = e.message; }
    }

    async function borrarArchivo(id) {
        const a = buscarArchivo(id);
        if (!a || !confirm(`¿Quitar «${a.nombre}» de la lista?\nEl archivo del servidor no se toca.`)) return;
        try {
            await pedir(`${base()}/archivos/${id}`, { method: 'DELETE' });
            if (actual && actual.id === id) cerrarVisor();
            await cargar();
        } catch (e) { aviso(e.message, 'danger'); }
    }

    function cerrarVisor() {
        detenerSeguir(); actual = null; texto = '';
        $('lt-visor').classList.add('d-none'); $('lt-vacio').classList.remove('d-none');
    }

    return { cargar, abrir, recargar, antiguas, todo, seguir, filtrar, ajustar, copiar,
             formGrupo, guardarGrupo, borrarGrupo, formArchivo, guardarArchivo, borrarArchivo, sugerir };
})();
// En la carga completa, Vue monta #init-vue hasta que llegan los permisos y redibuja lo que
// haya dentro: se pinta cuando ya está montado (__vue_app__). Con la navegación SPA ya lo está.
(function arrancar(intentos) {
    const vue = document.getElementById('init-vue');
    if ((vue && vue.__vue_app__) || intentos >= 100) { window.logsTerceros.cargar(); return; }
    setTimeout(() => arrancar(intentos + 1), 100);
})(0);
</script>
@endpush
