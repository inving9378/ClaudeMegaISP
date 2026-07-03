/* Talento Portal Técnico — shell PWA (Quasar UMD + Vue 3).
 * Sub-paso 1: chrome (header teal, nav inferior, tema por usuario, service worker).
 * Sub-paso 2: "Mi día" real — check-in/checkout geocercado + lista de OTs del día.
 *   Reutiliza /talento/portal/* que a su vez delega en la capa de servicios existente
 *   (AttendanceService, OrdenTrabajoUnifiedService::summaryForHoy).
 *   Las tarjetas de OT son navegables y llevan el discriminador de origen
 *   (work_order|task); el DETALLE completo (evidencia/firma/acciones) lo construye
 *   el Sub-paso 3. No forma parte del bundle del admin ni del interceptor spa-nav. */
(function () {
    'use strict';

    const CFG = window.__PORTAL__ || {};
    const { createApp, ref, computed, onMounted } = Vue;

    async function apiFetch(url, options) {
        const opts = Object.assign({ headers: {}, credentials: 'same-origin' }, options || {});
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': CFG.csrf }, opts.headers);
        if (opts.body && typeof opts.body !== 'string') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        const res = await fetch(url, opts);
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        return { ok: res.ok, status: res.status, data };
    }

    function getPosition() {
        return new Promise((resolve, reject) => {
            if (!('geolocation' in navigator)) { reject({ code: 'unsupported' }); return; }
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve(pos),
                (err) => reject(err),
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }

    function fmtHora(dt) {
        if (!dt) return '';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    // Estado de OT → etiqueta + color (cubre WO y tasks ya mapeadas por el servicio).
    function statusInfo(status) {
        const map = {
            pending:            { label: 'Pendiente',    color: 'grey-6' },
            in_progress:        { label: 'En progreso',  color: 'blue-7' },
            completed:          { label: 'Completada',   color: 'teal-6' },
            incidencia:         { label: 'Incidencia',   color: 'orange-8' },
            pending_activation: { label: 'Por activar',  color: 'purple-6' },
            active:             { label: 'Activa',        color: 'green-7' },
            survey_pending:     { label: 'Encuesta',     color: 'blue-grey-6' },
            validated:          { label: 'Validada',     color: 'green-8' },
        };
        return map[status] || { label: status || '—', color: 'grey-7' };
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        const secure = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);
        if (!secure) {
            console.info('[talento-portal] SW omitido: se requiere HTTPS para instalar la PWA (pendiente subdominio + SSL).');
            return;
        }
        navigator.serviceWorker.register(CFG.endpoints.sw, { scope: '/talento/portal/' })
            .then(() => console.info('[talento-portal] service worker registrado.'))
            .catch((e) => console.warn('[talento-portal] SW no se registró:', e && e.message));
    }

    const App = {
        setup() {
            const $q = Quasar.useQuasar();

            const colaborador = ref(CFG.colaborador || null);
            const tab = ref('inicio');
            const dark = ref(CFG.theme === 'dark');
            const savingTheme = ref(false);

            // Asistencia
            const asistencia = ref(null);
            const cargandoAsistencia = ref(true);
            const accionAsistencia = ref(false);

            // OTs del día
            const ots = ref([]);
            const cargandoOts = ref(true);
            const otSeleccionada = ref(null); // {origen, id, ...} — resumen de la tarjeta
            const detalle = ref(null);        // detalle completo (ot, checklist, evidencias, firmas)
            const cargandoDetalle = ref(false);

            $q.dark.set(dark.value);

            const nombre = computed(() =>
                colaborador.value && colaborador.value.nombre ? colaborador.value.nombre : 'Colaborador');
            const tipoLabel = computed(() => {
                const t = colaborador.value && colaborador.value.tipo;
                const map = { technician: 'Técnico', supervisor: 'Supervisor', ayudante: 'Ayudante', interno: 'Interno' };
                return map[t] || (t || 'Técnico');
            });

            const yaEntro = computed(() => !!(asistencia.value && asistencia.value.check_in_at));
            const yaSalio = computed(() => !!(asistencia.value && asistencia.value.check_out_at));
            const turnoAbierto = computed(() => yaEntro.value && !yaSalio.value);

            async function cargarAsistencia() {
                if (!colaborador.value) { cargandoAsistencia.value = false; return; }
                cargandoAsistencia.value = true;
                const { ok, data } = await apiFetch('/talento/portal/asistencia/hoy');
                asistencia.value = (ok && data) ? data : null;
                cargandoAsistencia.value = false;
            }

            async function cargarOts() {
                if (!colaborador.value) { cargandoOts.value = false; return; }
                cargandoOts.value = true;
                const { ok, data } = await apiFetch('/talento/portal/ots/hoy');
                ots.value = (ok && data && Array.isArray(data.data)) ? data.data : [];
                cargandoOts.value = false;
            }

            async function registrarEntrada() {
                if (accionAsistencia.value) return;
                accionAsistencia.value = true;
                let pos;
                try { pos = await getPosition(); }
                catch (err) {
                    accionAsistencia.value = false;
                    const msg = err && err.code === 1
                        ? 'Necesitamos tu ubicación para el check-in. Activa el permiso de ubicación e inténtalo de nuevo.'
                        : 'No pudimos obtener tu ubicación. Revisa el GPS e inténtalo de nuevo.';
                    $q.notify({ type: 'negative', message: msg, timeout: 5000 });
                    return;
                }
                const { latitude, longitude, accuracy } = pos.coords;
                const { ok, data } = await apiFetch('/talento/portal/asistencia/checkin', {
                    method: 'POST', body: { lat: latitude, lng: longitude, accuracy: accuracy },
                });
                accionAsistencia.value = false;
                if (!ok) { $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo registrar la entrada.' }); return; }
                await cargarAsistencia();
                if (data.status === 'already_open') {
                    $q.notify({ type: 'info', message: 'Ya tenías una entrada activa hoy.' });
                } else if (data.flagged) {
                    $q.notify({
                        type: 'warning', icon: 'flag',
                        message: 'Registrado — marcado para revisión' + (data.flag_reason ? ' (' + data.flag_reason + ')' : ''),
                        caption: 'Precisión ~' + Math.round(accuracy || 0) + ' m', timeout: 6000,
                    });
                } else {
                    $q.notify({ type: 'positive', icon: 'check_circle', message: 'Entrada registrada' + (data.geocerca ? ' en ' + data.geocerca : ''), timeout: 4000 });
                }
            }

            async function registrarSalida() {
                if (accionAsistencia.value) return;
                accionAsistencia.value = true;
                let body = {};
                try { const pos = await getPosition(); body = { lat: pos.coords.latitude, lng: pos.coords.longitude }; }
                catch (e) { /* salida sin coords: permitido */ }
                const { ok, data } = await apiFetch('/talento/portal/asistencia/checkout', { method: 'POST', body });
                accionAsistencia.value = false;
                if (!ok) { $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo registrar la salida.' }); return; }
                await cargarAsistencia();
                $q.notify({ type: 'positive', icon: 'logout', message: 'Salida registrada. ¡Buen trabajo!', timeout: 4000 });
            }

            // Navegación al detalle + carga del detalle completo.
            async function cargarDetalle(origen, id) {
                cargandoDetalle.value = true;
                detalle.value = null;
                const { ok, data } = await apiFetch('/talento/portal/ot/' + origen + '/' + id);
                detalle.value = ok ? data : null;
                cargandoDetalle.value = false;
                if (!ok) $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo cargar la OT.' });
            }
            function abrirDetalle(ot) { otSeleccionada.value = ot; cargarDetalle(ot.origen, ot.id); }
            function cerrarDetalle() { otSeleccionada.value = null; detalle.value = null; }
            function recargarDetalle() {
                if (otSeleccionada.value) cargarDetalle(otSeleccionada.value.origen, otSeleccionada.value.id);
            }

            const accionOt = ref(false);
            function otUrl(sufijo) {
                return '/talento/portal/ot/' + detalle.value.ot.origen + '/' + detalle.value.ot.id + (sufijo || '');
            }

            async function iniciarOt() {
                if (!detalle.value || accionOt.value) return;
                accionOt.value = true;
                const { ok, data } = await apiFetch(otUrl('/iniciar'), { method: 'POST' });
                accionOt.value = false;
                if (!ok) { $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo iniciar.' }); return; }
                $q.notify({ type: 'positive', icon: 'play_arrow', message: 'OT iniciada.' });
                await recargarDetalle(); cargarOts();
            }

            async function completarOt() {
                if (!detalle.value || accionOt.value) return;
                accionOt.value = true;
                const { ok, data } = await apiFetch(otUrl('/completar'), { method: 'POST', body: {} });
                accionOt.value = false;
                if (!ok) {
                    let msg = (data && data.message) || 'No se pudo completar.';
                    if (data && Array.isArray(data.faltantes) && data.faltantes.length) {
                        msg += ' Faltan: ' + data.faltantes.map((f) => f.name).join(', ') + '.';
                    }
                    $q.notify({ type: 'warning', icon: 'block', message: msg, timeout: 7000 });
                    return;
                }
                $q.notify({ type: 'positive', icon: 'check_circle', message: 'OT completada.' });
                await recargarDetalle(); cargarOts();
            }

            async function aceptarOt() {
                if (!detalle.value || accionOt.value) return;
                accionOt.value = true;
                const { ok, data } = await apiFetch(otUrl('/aceptar'), { method: 'POST' });
                accionOt.value = false;
                if (!ok) { $q.notify({ type: 'warning', icon: 'block', message: (data && data.message) || 'No se pudo aceptar.', timeout: 6000 }); return; }
                $q.notify({ type: 'positive', icon: 'verified', message: (data && data.message) || 'OT aceptada.', timeout: 5000 });
                await recargarDetalle(); cargarOts();
            }

            // ── Captura de evidencia anti-fraude (cámara viva) ──
            const capturaAbierta = ref(false);
            const camaraSoportada = ref(true);
            const camaraError = ref(null);
            const streamActivo = ref(false);
            const fotoTomada = ref(false);
            const tipoEvidencia = ref(null);
            const dbmValor = ref(null);
            const justificacionValor = ref('');
            const enviandoEvidencia = ref(false);
            let mediaStream = null;

            // Tipos capturables: los del checklist que no son firma (la firma va por pad).
            const tiposCapturables = computed(() =>
                (detalle.value ? detalle.value.checklist : [])
                    .filter((c) => !c.es_firma)
                    .map((c) => ({ label: c.nombre + (c.subido ? ' ✓' : ''), value: c.evidence_type_id, ...c })));
            const tipoSel = computed(() =>
                tiposCapturables.value.find((t) => t.value === tipoEvidencia.value) || null);

            function uuid() {
                if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                    const r = (Math.random() * 16) | 0; return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
                });
            }

            async function abrirCaptura() {
                camaraError.value = null; fotoTomada.value = false; dbmValor.value = null;
                justificacionValor.value = ''; tipoEvidencia.value = null;
                const pendientes = tiposCapturables.value.filter((t) => !t.subido);
                if (pendientes.length) tipoEvidencia.value = pendientes[0].value;
                capturaAbierta.value = true;
                // getUserMedia sólo en contexto seguro (https/localhost).
                const secure = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);
                if (!secure || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    camaraSoportada.value = false;
                    camaraError.value = 'La cámara requiere HTTPS. Disponible al publicar el portal con SSL.';
                    return;
                }
                camaraSoportada.value = true;
                try {
                    mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                    streamActivo.value = true;
                    Vue.nextTick(() => {
                        const v = document.getElementById('tp-video');
                        if (v) { v.srcObject = mediaStream; v.play().catch(() => {}); }
                    });
                } catch (e) {
                    camaraError.value = e && e.name === 'NotAllowedError'
                        ? 'Permiso de cámara denegado. Actívalo para capturar evidencia.'
                        : 'No se pudo abrir la cámara.';
                }
            }

            function detenerCamara() {
                if (mediaStream) { mediaStream.getTracks().forEach((t) => t.stop()); mediaStream = null; }
                streamActivo.value = false;
            }

            function capturarFrame() {
                const v = document.getElementById('tp-video');
                const c = document.getElementById('tp-canvas');
                if (!v || !c) return;
                c.width = v.videoWidth || 720; c.height = v.videoHeight || 960;
                const ctx = c.getContext('2d');
                ctx.drawImage(v, 0, 0, c.width, c.height);
                // Overlay de PREVIEW (no autoritativo; el server quema el watermark real).
                const now = new Date();
                const lines = ['Talento Meganet', now.toLocaleString('es-MX'), 'PREVIEW — el servidor sella la marca'];
                ctx.fillStyle = 'rgba(0,0,0,0.5)'; ctx.fillRect(0, c.height - 66, c.width, 66);
                ctx.fillStyle = '#fff'; ctx.font = '16px sans-serif';
                lines.forEach((l, i) => ctx.fillText(l, 10, c.height - 44 + i * 20));
                fotoTomada.value = true;
                detenerCamara();
            }

            function reintentarFoto() { fotoTomada.value = false; abrirCaptura(); }

            function cerrarCaptura() { detenerCamara(); capturaAbierta.value = false; fotoTomada.value = false; }

            async function enviarEvidencia() {
                if (enviandoEvidencia.value || !fotoTomada.value || !tipoEvidencia.value) return;
                if (tipoSel.value && tipoSel.value.requiere_justificacion && !justificacionValor.value.trim()) {
                    $q.notify({ type: 'warning', message: 'Este tipo de evidencia requiere justificación.' }); return;
                }
                if (tipoSel.value && tipoSel.value.es_lectura_dbm && (dbmValor.value === null || dbmValor.value === '')) {
                    $q.notify({ type: 'warning', message: 'Captura la lectura dBm.' }); return;
                }
                enviandoEvidencia.value = true;
                let pos;
                try { pos = await getPosition(); }
                catch (e) {
                    enviandoEvidencia.value = false;
                    $q.notify({ type: 'negative', message: 'Necesitamos tu ubicación para subir la evidencia.' }); return;
                }
                const canvas = document.getElementById('tp-canvas');
                canvas.toBlob(async (blob) => {
                    const fd = new FormData();
                    fd.append('foto', blob, 'evidencia.jpg');
                    fd.append('evidence_type_id', String(tipoEvidencia.value));
                    fd.append('lat', pos.coords.latitude);
                    fd.append('lng', pos.coords.longitude);
                    if (pos.coords.accuracy != null) fd.append('accuracy', pos.coords.accuracy);
                    if (tipoSel.value && tipoSel.value.es_lectura_dbm) fd.append('potencia_dbm', dbmValor.value);
                    if (justificacionValor.value.trim()) fd.append('justificacion', justificacionValor.value.trim());
                    fd.append('client_uuid', uuid());
                    try {
                        const res = await fetch(otUrl('/evidencia'), {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        const data = await res.json().catch(() => null);
                        enviandoEvidencia.value = false;
                        if (!res.ok) { $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo subir la evidencia.' }); return; }
                        $q.notify({ type: 'positive', icon: 'photo_camera', message: 'Evidencia registrada (watermark del servidor).' });
                        cerrarCaptura();
                        await recargarDetalle();
                    } catch (e) {
                        enviandoEvidencia.value = false;
                        $q.notify({ type: 'negative', message: 'Error de red al subir la evidencia.' });
                    }
                }, 'image/jpeg', 0.9);
            }

            // ── Firmas (signature-pad) ──
            const firmaAbierta = ref(false);
            const firmaSigner = ref('client');
            const guardandoFirma = ref(false);
            let sigCtx = null, sigDibujando = false, sigTocada = false;

            function initSigPad() {
                const c = document.getElementById('tp-sig');
                if (!c) return;
                const rect = c.getBoundingClientRect();
                c.width = rect.width; c.height = rect.height;
                sigCtx = c.getContext('2d');
                sigCtx.lineWidth = 2.5; sigCtx.lineCap = 'round'; sigCtx.strokeStyle = '#111';
                sigCtx.clearRect(0, 0, c.width, c.height);
                sigTocada = false;
            }
            function sigPos(e) {
                const c = document.getElementById('tp-sig');
                const r = c.getBoundingClientRect();
                return { x: e.clientX - r.left, y: e.clientY - r.top };
            }
            function sigStart(e) { e.preventDefault(); sigDibujando = true; sigTocada = true; const p = sigPos(e); sigCtx.beginPath(); sigCtx.moveTo(p.x, p.y); }
            function sigMove(e) { if (!sigDibujando) return; e.preventDefault(); const p = sigPos(e); sigCtx.lineTo(p.x, p.y); sigCtx.stroke(); }
            function sigEnd() { sigDibujando = false; }
            function limpiarFirma() { if (sigCtx) { const c = document.getElementById('tp-sig'); sigCtx.clearRect(0, 0, c.width, c.height); } sigTocada = false; }
            function abrirFirma(signer) { firmaSigner.value = signer; firmaAbierta.value = true; Vue.nextTick(initSigPad); }

            async function guardarFirma() {
                if (guardandoFirma.value) return;
                if (!sigTocada) { $q.notify({ type: 'warning', message: 'Dibuja la firma antes de guardar.' }); return; }
                guardandoFirma.value = true;
                const c = document.getElementById('tp-sig');
                const body = { signer_type: firmaSigner.value, signature_data: c.toDataURL('image/png') };
                try { const pos = await getPosition(); body.lat = pos.coords.latitude; body.lng = pos.coords.longitude; } catch (e) {}
                const { ok, data } = await apiFetch(otUrl('/firma'), { method: 'POST', body });
                guardandoFirma.value = false;
                if (!ok) { $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo guardar la firma.' }); return; }
                $q.notify({ type: 'positive', icon: 'draw', message: 'Firma guardada.' });
                firmaAbierta.value = false;
                await recargarDetalle();
            }

            function toggleDark() {
                dark.value = !dark.value;
                const theme = dark.value ? 'dark' : 'light';
                $q.dark.set(dark.value);
                document.documentElement.classList.toggle('portal-dark', dark.value);
                document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark.value ? '#0f1620' : '#0d9488');
                try { localStorage.setItem('talentoPortalTheme', theme); } catch (e) {}
                savingTheme.value = true;
                apiFetch(CFG.endpoints.saveTheme, { method: 'POST', body: { theme } })
                    .catch(() => {}).finally(() => { savingTheme.value = false; });
            }

            function logout() {
                $q.dialog({
                    title: 'Cerrar sesión', message: '¿Salir del portal técnico?',
                    cancel: { label: 'Cancelar', flat: true },
                    ok: { label: 'Salir', color: 'negative', unelevated: true }, persistent: true,
                }).onOk(() => {
                    const f = document.createElement('form');
                    f.method = 'POST'; f.action = CFG.endpoints.logout;
                    const t = document.createElement('input');
                    t.type = 'hidden'; t.name = '_token'; t.value = CFG.csrf;
                    f.appendChild(t); document.body.appendChild(f); f.submit();
                });
            }

            onMounted(() => {
                registerServiceWorker();
                cargarAsistencia();
                cargarOts();
            });

            // ── "Mi dinero" (Bloque 2) — Cuenta + Desglose ──────────────────────────
            const dineroTab = ref('cuenta');
            const cuenta = ref(null);
            const desglose = ref(null);
            const cargandoDinero = ref(false);
            const dineroCargado = ref(false);
            const CONCEPTO_LABEL = {
                salary_base: 'Sueldo base', overproduction: 'Sobreproducción', bonus: 'Bonos',
                penalty: 'Penalización', fund_contribution: 'Aporte a fondo', loan_repayment: 'Pago de préstamo',
                advance: 'Adelanto', adjustment: 'Ajuste', embajador: 'Embajadores',
            };
            // Formato de presentación (el backend ya calculó/redondeó el monto; el front NO recalcula).
            function money(n) {
                return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function conceptoLabel(c) { return CONCEPTO_LABEL[c] || c; }
            function pctCuota(d) { return (d && d.quota) ? Math.min(1, (d.units || 0) / d.quota) : 0; }

            async function cargarDinero() {
                if (!colaborador.value) { cargandoDinero.value = false; return; }
                cargandoDinero.value = true;
                const [c, d] = await Promise.all([
                    apiFetch('/talento/portal/dinero/cuenta'),
                    apiFetch('/talento/portal/dinero/desglose'),
                ]);
                cuenta.value = (c.ok && c.data) ? c.data : null;
                desglose.value = (d.ok && d.data) ? d.data : null;
                cargandoDinero.value = false;
                dineroCargado.value = true;
            }
            // Cierra el detalle de OT al cambiar de tab; carga "Mi dinero" al abrirlo por primera vez.
            function onTabChange(val) {
                cerrarDetalle();
                if (val === 'dinero' && !dineroCargado.value) cargarDinero();
            }

            return {
                colaborador, tab, dark, savingTheme, nombre, tipoLabel,
                dineroTab, cuenta, desglose, cargandoDinero, cargarDinero, money, conceptoLabel, pctCuota, onTabChange,
                asistencia, cargandoAsistencia, accionAsistencia, yaEntro, yaSalio, turnoAbierto,
                ots, cargandoOts, otSeleccionada, detalle, cargandoDetalle, accionOt,
                fmtHora, statusInfo,
                registrarEntrada, registrarSalida, abrirDetalle, cerrarDetalle, recargarDetalle,
                iniciarOt, completarOt, aceptarOt, toggleDark, logout,
                capturaAbierta, camaraSoportada, camaraError, streamActivo, fotoTomada,
                tipoEvidencia, tiposCapturables, tipoSel, dbmValor, justificacionValor, enviandoEvidencia,
                abrirCaptura, capturarFrame, reintentarFoto, cerrarCaptura, enviarEvidencia,
                firmaAbierta, firmaSigner, guardandoFirma,
                abrirFirma, sigStart, sigMove, sigEnd, limpiarFirma, guardarFirma,
            };
        },

        template: `
        <q-layout view="hHh lpr fFf">
            <q-header elevated class="tp-header">
                <q-toolbar>
                    <q-btn v-if="otSeleccionada" flat round dense icon="arrow_back" @click="cerrarDetalle" aria-label="Volver" />
                    <q-avatar v-else size="30px" color="white" text-color="teal-8"><q-icon name="engineering" /></q-avatar>
                    <q-toolbar-title>
                        <template v-if="otSeleccionada">OT #{{ otSeleccionada.folio }}</template>
                        <template v-else>Talento Campo</template>
                        <div class="tp-colab-chip" v-if="colaborador && !otSeleccionada">{{ nombre }} · {{ tipoLabel }}</div>
                    </q-toolbar-title>
                    <q-btn flat round dense :icon="dark ? 'light_mode' : 'dark_mode'" :loading="savingTheme" @click="toggleDark" aria-label="Cambiar tema" />
                    <q-btn flat round dense icon="logout" @click="logout" aria-label="Salir" />
                </q-toolbar>
            </q-header>

            <q-page-container class="tp-page">
                <q-page class="tp-page">

                    <!-- Mi día -->
                    <div v-show="tab==='inicio'" class="q-pa-md">
                        <q-banner v-if="!colaborador" class="bg-orange-1 text-orange-9 q-mb-md" rounded>
                            <template #avatar><q-icon name="badge" color="orange-9" /></template>
                            Tu usuario no tiene un perfil de colaborador activo. El portal técnico es para
                            personal de campo; pídele a un administrador que te vincule un perfil de Talento.
                        </q-banner>

                        <!-- Detalle de OT -->
                        <template v-else-if="otSeleccionada">
                            <div v-if="cargandoDetalle" class="row items-center tp-muted q-py-lg justify-center">
                                <q-spinner size="26px" color="teal-6" class="q-mr-sm" /> Cargando OT…
                            </div>

                            <template v-else-if="detalle">
                                <q-card flat bordered class="tp-card q-mb-md">
                                    <q-card-section>
                                        <div class="text-overline tp-muted">{{ detalle.ot.tipo }}</div>
                                        <div class="text-h6">OT #{{ detalle.ot.folio }}</div>
                                        <q-chip dense :color="statusInfo(detalle.ot.status).color" text-color="white" class="q-mt-xs">
                                            {{ statusInfo(detalle.ot.status).label }}
                                        </q-chip>
                                    </q-card-section>
                                    <q-separator />
                                    <q-list>
                                        <q-item v-if="detalle.ot.cliente">
                                            <q-item-section avatar><q-icon name="person" color="teal-6" /></q-item-section>
                                            <q-item-section><q-item-label caption>Cliente</q-item-label><q-item-label>{{ detalle.ot.cliente }}</q-item-label></q-item-section>
                                        </q-item>
                                        <q-item v-if="detalle.ot.direccion">
                                            <q-item-section avatar><q-icon name="place" color="teal-6" /></q-item-section>
                                            <q-item-section><q-item-label caption>Dirección</q-item-label><q-item-label>{{ detalle.ot.direccion }}</q-item-label></q-item-section>
                                        </q-item>
                                        <q-item v-if="detalle.ot.telefono">
                                            <q-item-section avatar><q-icon name="call" color="teal-6" /></q-item-section>
                                            <q-item-section><q-item-label caption>Teléfono</q-item-label><q-item-label>{{ detalle.ot.telefono }}</q-item-label></q-item-section>
                                        </q-item>
                                        <q-item v-if="detalle.ot.notas">
                                            <q-item-section avatar><q-icon name="notes" color="teal-6" /></q-item-section>
                                            <q-item-section><q-item-label caption>Notas</q-item-label><q-item-label>{{ detalle.ot.notas }}</q-item-label></q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-card>

                                <!-- Acción principal según estado -->
                                <q-card flat bordered class="tp-card q-mb-md">
                                    <q-card-section>
                                        <template v-if="detalle.handoff">
                                            <div class="row items-center text-teal-7">
                                                <q-icon name="hourglass_top" size="22px" class="q-mr-sm" />
                                                <div>OT aceptada — <b>en espera de activación</b>.</div>
                                            </div>
                                        </template>
                                        <q-btn v-else-if="detalle.ot.status==='pending'" unelevated color="teal-6"
                                               icon="play_arrow" label="Iniciar OT" class="full-width"
                                               :loading="accionOt" @click="iniciarOt" />
                                        <template v-else-if="detalle.ot.status==='in_progress'">
                                            <q-btn unelevated color="teal-7" icon="check_circle" label="Completar OT"
                                                   class="full-width" :loading="accionOt" @click="completarOt" />
                                            <div class="text-caption tp-muted q-mt-sm">
                                                Se validará la evidencia obligatoria y el umbral dBm antes de cerrar.
                                            </div>
                                        </template>
                                        <template v-else-if="detalle.ot.status==='completed'">
                                            <q-btn unelevated color="teal-6" icon="verified" label="Aceptar OT"
                                                   class="full-width" :loading="accionOt" @click="aceptarOt"
                                                   :disable="!(detalle.firmas.technician && detalle.firmas.client)" />
                                            <div class="text-caption tp-muted q-mt-sm">
                                                <span v-if="!(detalle.firmas.technician && detalle.firmas.client)">Requiere firma de técnico y cliente.</span>
                                                <span v-else>Al aceptar, la OT pasa a espera de activación (la activa el área de activaciones).</span>
                                            </div>
                                        </template>
                                        <div v-else class="row items-center tp-muted">
                                            <q-icon name="info" class="q-mr-sm" />
                                            OT en estado «{{ statusInfo(detalle.ot.status).label }}».
                                        </div>
                                    </q-card-section>
                                </q-card>

                                <!-- Checklist de evidencia requerida -->
                                <q-card flat bordered class="tp-card q-mb-md">
                                    <q-card-section class="q-pb-none">
                                        <div class="row items-center">
                                            <q-icon name="checklist" color="teal-6" size="22px" class="q-mr-sm" />
                                            <div class="text-subtitle1 text-weight-medium">Evidencia requerida</div>
                                        </div>
                                    </q-card-section>
                                    <q-list>
                                        <q-item v-for="c in detalle.checklist" :key="c.evidence_type_id">
                                            <q-item-section avatar>
                                                <q-icon :name="c.subido ? 'check_circle' : 'radio_button_unchecked'"
                                                        :color="c.subido ? 'positive' : 'grey-5'" />
                                            </q-item-section>
                                            <q-item-section>
                                                <q-item-label>{{ c.nombre }}</q-item-label>
                                                <q-item-label caption v-if="c.es_lectura_dbm || c.es_firma">
                                                    <span v-if="c.es_lectura_dbm">Requiere lectura dBm</span>
                                                    <span v-if="c.es_firma">Firma</span>
                                                </q-item-label>
                                            </q-item-section>
                                            <q-item-section side v-if="c.subido">
                                                <q-badge color="positive" label="Listo" />
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                    <q-card-actions v-if="detalle.ot.status==='in_progress'">
                                        <q-btn unelevated color="teal-6" icon="photo_camera"
                                               label="Capturar evidencia" class="full-width" @click="abrirCaptura" />
                                    </q-card-actions>
                                </q-card>

                                <!-- Evidencia capturada -->
                                <q-card flat bordered class="tp-card">
                                    <q-card-section class="q-pb-none">
                                        <div class="row items-center">
                                            <q-icon name="photo_library" color="teal-6" size="22px" class="q-mr-sm" />
                                            <div class="text-subtitle1 text-weight-medium">Evidencia capturada</div>
                                            <q-space />
                                            <q-badge v-if="detalle.evidencias.length" color="teal-6" :label="detalle.evidencias.length" />
                                        </div>
                                    </q-card-section>
                                    <q-card-section v-if="!detalle.evidencias.length" class="tp-muted text-caption">
                                        Aún no hay evidencia capturada.
                                    </q-card-section>
                                    <q-list v-else separator>
                                        <q-item v-for="ev in detalle.evidencias" :key="ev.id">
                                            <q-item-section avatar><q-icon name="image" color="teal-6" /></q-item-section>
                                            <q-item-section>
                                                <q-item-label>{{ ev.tipo_nombre || ('Tipo #' + ev.type_id) }}</q-item-label>
                                                <q-item-label caption>
                                                    {{ fmtHora(ev.created_at) }}
                                                    <span v-if="ev.potencia_dbm !== null && ev.potencia_dbm !== undefined"> · {{ Math.round(ev.potencia_dbm * 10) / 10 }} dBm</span>
                                                </q-item-label>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-card>

                                <!-- Firmas -->
                                <q-card v-if="['in_progress','completed'].includes(detalle.ot.status)" flat bordered class="tp-card q-mt-md">
                                    <q-card-section class="q-pb-none">
                                        <div class="row items-center">
                                            <q-icon name="draw" color="teal-6" size="22px" class="q-mr-sm" />
                                            <div class="text-subtitle1 text-weight-medium">Firmas</div>
                                        </div>
                                    </q-card-section>
                                    <q-list>
                                        <q-item>
                                            <q-item-section avatar>
                                                <q-icon :name="detalle.firmas.technician ? 'check_circle' : 'radio_button_unchecked'"
                                                        :color="detalle.firmas.technician ? 'positive' : 'grey-5'" />
                                            </q-item-section>
                                            <q-item-section><q-item-label>Firma del técnico</q-item-label></q-item-section>
                                            <q-item-section side>
                                                <q-btn dense flat color="teal-6" :label="detalle.firmas.technician ? 'Re-firmar' : 'Firmar'" @click="abrirFirma('technician')" />
                                            </q-item-section>
                                        </q-item>
                                        <q-item>
                                            <q-item-section avatar>
                                                <q-icon :name="detalle.firmas.client ? 'check_circle' : 'radio_button_unchecked'"
                                                        :color="detalle.firmas.client ? 'positive' : 'grey-5'" />
                                            </q-item-section>
                                            <q-item-section><q-item-label>Firma del cliente</q-item-label></q-item-section>
                                            <q-item-section side>
                                                <q-btn dense flat color="teal-6" :label="detalle.firmas.client ? 'Re-firmar' : 'Firmar'" @click="abrirFirma('client')" />
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-card>
                            </template>

                            <q-banner v-else class="bg-red-1 text-red-9 tp-card" rounded>
                                <template #avatar><q-icon name="error" color="red-9" /></template>
                                No se pudo cargar el detalle de la OT.
                            </q-banner>
                        </template>

                        <template v-else>
                            <q-card flat bordered class="tp-card q-mb-md">
                                <q-card-section class="row items-center no-wrap">
                                    <q-avatar color="teal-6" text-color="white" size="46px"><q-icon name="person" /></q-avatar>
                                    <div class="q-ml-md">
                                        <div class="text-subtitle1 text-weight-medium">Hola, {{ nombre }}</div>
                                        <div class="tp-muted text-caption">{{ tipoLabel }}</div>
                                    </div>
                                </q-card-section>
                            </q-card>

                            <!-- Asistencia -->
                            <q-card flat bordered class="tp-card q-mb-md">
                                <q-card-section>
                                    <div class="row items-center q-mb-sm">
                                        <q-icon name="schedule" color="teal-6" size="22px" class="q-mr-sm" />
                                        <div class="text-subtitle1 text-weight-medium">Asistencia de hoy</div>
                                    </div>
                                    <div v-if="cargandoAsistencia" class="row items-center tp-muted q-py-sm">
                                        <q-spinner size="20px" color="teal-6" class="q-mr-sm" /> Consultando…
                                    </div>
                                    <template v-else>
                                        <div v-if="yaSalio" class="tp-muted">
                                            <q-icon name="task_alt" color="positive" /> Turno cerrado.
                                            Entrada {{ fmtHora(asistencia.check_in_at) }} · Salida {{ fmtHora(asistencia.check_out_at) }}.
                                        </div>
                                        <template v-else-if="turnoAbierto">
                                            <q-banner v-if="asistencia.stale_day" dense rounded class="bg-orange-1 text-orange-9 q-mb-sm">
                                                <template #avatar><q-icon name="history_toggle_off" color="orange-9" /></template>
                                                Tienes un turno de un día anterior sin cerrar. Registra tu salida para poder iniciar uno nuevo.
                                            </q-banner>
                                            <div class="row items-center q-gutter-x-sm q-mb-sm">
                                                <q-chip dense color="positive" text-color="white" icon="login">Entrada {{ fmtHora(asistencia.check_in_at) }}</q-chip>
                                                <q-chip v-if="asistencia.geocerca" dense outline color="teal-7" icon="place">{{ asistencia.geocerca }}</q-chip>
                                                <q-chip v-if="asistencia.flagged" dense color="orange-8" text-color="white" icon="flag">Marcado para revisión</q-chip>
                                            </div>
                                            <q-btn unelevated color="teal-7" icon="logout" label="Registrar salida" :loading="accionAsistencia" @click="registrarSalida" class="full-width" />
                                        </template>
                                        <template v-else>
                                            <div class="tp-muted q-mb-sm">Aún no registras tu entrada.</div>
                                            <q-btn unelevated color="teal-6" icon="my_location" label="Registrar entrada" :loading="accionAsistencia" @click="registrarEntrada" class="full-width" />
                                            <div class="text-caption tp-muted q-mt-sm">Usaremos tu ubicación para validar la geocerca en el servidor.</div>
                                        </template>
                                    </template>
                                </q-card-section>
                            </q-card>

                            <!-- OTs del día -->
                            <div class="row items-center q-mb-sm q-px-xs">
                                <q-icon name="assignment" color="teal-6" size="22px" class="q-mr-sm" />
                                <div class="text-subtitle1 text-weight-medium">Órdenes de hoy</div>
                                <q-space />
                                <q-badge v-if="!cargandoOts && ots.length" color="teal-6" :label="ots.length" />
                            </div>

                            <div v-if="cargandoOts" class="row items-center tp-muted q-py-md q-px-xs">
                                <q-spinner size="20px" color="teal-6" class="q-mr-sm" /> Cargando órdenes…
                            </div>

                            <div v-else-if="!ots.length" class="tp-empty">
                                <q-icon name="event_available" class="tp-empty-icon" />
                                <div class="text-subtitle1">Sin órdenes para hoy</div>
                                <div class="text-caption">No tienes órdenes de trabajo programadas para hoy.</div>
                            </div>

                            <div v-else class="q-gutter-y-sm">
                                <q-card v-for="ot in ots" :key="ot.origen + '-' + ot.id" flat bordered
                                        class="tp-card cursor-pointer" @click="abrirDetalle(ot)">
                                    <q-card-section class="q-pb-xs">
                                        <div class="row items-center no-wrap">
                                            <div class="col">
                                                <div class="text-weight-medium">#{{ ot.folio }} · {{ ot.tipo }}</div>
                                                <div class="tp-muted text-caption ellipsis" v-if="ot.cliente">{{ ot.cliente }}</div>
                                            </div>
                                            <q-chip dense :color="statusInfo(ot.status).color" text-color="white">{{ statusInfo(ot.status).label }}</q-chip>
                                            <q-icon name="chevron_right" color="grey-6" size="22px" />
                                        </div>
                                    </q-card-section>
                                    <q-card-section v-if="ot.direccion" class="q-pt-none">
                                        <div class="row items-center tp-muted text-caption">
                                            <q-icon name="place" size="15px" class="q-mr-xs" /><span class="ellipsis">{{ ot.direccion }}</span>
                                        </div>
                                    </q-card-section>
                                </q-card>
                            </div>
                        </template>
                    </div>

                    <!-- Mi dinero -->
                    <div v-show="tab==='dinero'">
                        <div class="q-px-md q-pt-md q-pb-none">
                            <div class="text-caption text-grey-7">Semana de pago</div>
                            <div class="text-subtitle2" v-if="cuenta || desglose">
                                {{ (cuenta || desglose).period_start }} → {{ (cuenta || desglose).period_end }}
                            </div>
                        </div>
                        <q-tabs v-model="dineroTab" no-caps dense active-color="teal-6" indicator-color="teal-6" align="justify" class="q-mt-xs">
                            <q-tab name="cuenta"    label="Cuenta" />
                            <q-tab name="desglose"  label="Desglose" />
                            <q-tab name="fondo"     label="Fondo" />
                            <q-tab name="prestamos" label="Préstamos" />
                        </q-tabs>

                        <div v-if="cargandoDinero" class="flex flex-center q-pa-xl">
                            <q-spinner-dots size="40px" color="teal-6" />
                        </div>

                        <q-tab-panels v-else v-model="dineroTab" animated>
                            <!-- CUENTA: ledger liquidado del período agrupado por concepto + neto -->
                            <q-tab-panel name="cuenta" class="q-pa-md">
                                <template v-if="cuenta">
                                    <q-card flat bordered class="tp-card q-mb-md">
                                        <div class="text-caption text-grey-7">Neto del período</div>
                                        <div class="text-h5 text-teal-7">{{ money(cuenta.neto) }}</div>
                                        <div class="row justify-between text-caption text-grey-7 q-mt-xs">
                                            <span>Abonos {{ money(cuenta.total_credito) }}</span>
                                            <span>Descuentos {{ money(cuenta.total_debito) }}</span>
                                        </div>
                                    </q-card>
                                    <q-list separator v-if="cuenta.conceptos && cuenta.conceptos.length">
                                        <q-item v-for="(c,i) in cuenta.conceptos" :key="i">
                                            <q-item-section>
                                                <q-item-label>{{ conceptoLabel(c.concepto) }}</q-item-label>
                                                <q-item-label caption>{{ c.tipo==='credit' ? 'Abono' : 'Descuento' }}</q-item-label>
                                            </q-item-section>
                                            <q-item-section side>
                                                <span :class="c.tipo==='credit' ? 'text-teal-7' : 'text-negative'">
                                                    {{ c.tipo==='credit' ? '+' : '−' }}{{ money(c.subtotal) }}
                                                </span>
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                    <div v-else class="tp-empty">
                                        <q-icon name="receipt_long" class="tp-empty-icon" />
                                        <div class="text-subtitle1">Sin movimientos todavía</div>
                                        <div class="text-caption">Tu cuenta se llena cuando se cierra la semana de pago (el sábado). Mientras tanto, mirá el <b>Desglose</b> para ver tu producción.</div>
                                    </div>
                                </template>
                            </q-tab-panel>

                            <!-- DESGLOSE: proyección viva (unidades vs cuota, valor x unidad, sobreproducción) -->
                            <q-tab-panel name="desglose" class="q-pa-md">
                                <template v-if="desglose">
                                    <q-card flat bordered class="tp-card q-mb-md">
                                        <div class="text-caption text-grey-7">Pago proyectado (producción)</div>
                                        <div class="text-h5 text-teal-7">{{ money(desglose.projected_pay) }}</div>
                                        <div class="text-caption text-grey-7 q-mt-xs">
                                            Sueldo base {{ money(desglose.base_salary) }} · Sobreproducción {{ money(desglose.overproduction) }}
                                        </div>
                                    </q-card>
                                    <q-card flat bordered class="tp-card q-mb-md">
                                        <div class="row items-center justify-between">
                                            <div class="text-caption text-grey-7">Unidades de la semana</div>
                                            <div class="text-subtitle1">{{ desglose.units }} <span class="text-caption text-grey-7">/ cuota {{ desglose.quota }}</span></div>
                                        </div>
                                        <q-linear-progress :value="pctCuota(desglose)" color="teal-6" track-color="grey-3" size="10px" rounded class="q-mt-sm" />
                                        <div class="text-caption text-grey-7 q-mt-sm">
                                            Valor por unidad {{ money(desglose.value_per_unit) }}
                                            <span v-if="desglose.over_units > 0"> · {{ desglose.over_units }} unidades sobre cuota</span>
                                        </div>
                                        <div class="text-caption text-grey-6 q-mt-xs" v-if="desglose.units_task || desglose.units_external">
                                            Incluye {{ desglose.units_wo }} de OTs, {{ desglose.units_task }} de campo y {{ desglose.units_external }} de proyectos.
                                        </div>
                                    </q-card>
                                </template>
                            </q-tab-panel>

                            <!-- Fondo / Préstamos: Sub-paso 3 -->
                            <q-tab-panel name="fondo" class="tp-empty">
                                <q-icon name="savings" class="tp-empty-icon" />
                                <div class="text-subtitle1">Fondo de ahorro</div>
                                <div class="text-caption">Próxima entrega.</div>
                            </q-tab-panel>
                            <q-tab-panel name="prestamos" class="tp-empty">
                                <q-icon name="account_balance" class="tp-empty-icon" />
                                <div class="text-subtitle1">Préstamos</div>
                                <div class="text-caption">Próxima entrega.</div>
                            </q-tab-panel>
                        </q-tab-panels>
                    </div>

                    <!-- Proyectos -->
                    <div v-show="tab==='proyectos'" class="tp-empty">
                        <q-icon name="account_tree" class="tp-empty-icon" />
                        <div class="text-subtitle1">Mis proyectos</div>
                        <div class="text-caption">Planta externa: reporte diario, pool de actividades y reparto. Próxima entrega.</div>
                    </div>

                    <!-- Perfil -->
                    <div v-show="tab==='perfil'" class="q-pa-md">
                        <q-card flat bordered class="tp-card">
                            <q-list separator>
                                <q-item>
                                    <q-item-section avatar><q-icon name="person" color="teal-6" /></q-item-section>
                                    <q-item-section><q-item-label>{{ nombre }}</q-item-label><q-item-label caption>{{ tipoLabel }}</q-item-label></q-item-section>
                                </q-item>
                                <q-item v-if="colaborador && colaborador.email">
                                    <q-item-section avatar><q-icon name="mail" color="teal-6" /></q-item-section>
                                    <q-item-section><q-item-label>{{ colaborador.email }}</q-item-label></q-item-section>
                                </q-item>
                                <q-item tag="label">
                                    <q-item-section avatar><q-icon name="dark_mode" color="teal-6" /></q-item-section>
                                    <q-item-section><q-item-label>Modo oscuro</q-item-label></q-item-section>
                                    <q-item-section side><q-toggle :model-value="dark" @update:model-value="toggleDark" color="teal-6" /></q-item-section>
                                </q-item>
                                <q-item clickable @click="logout">
                                    <q-item-section avatar><q-icon name="logout" color="negative" /></q-item-section>
                                    <q-item-section><q-item-label class="text-negative">Cerrar sesión</q-item-label></q-item-section>
                                </q-item>
                            </q-list>
                        </q-card>
                    </div>

                </q-page>
            </q-page-container>

            <!-- Diálogo de captura de evidencia (cámara viva) -->
            <q-dialog v-model="capturaAbierta" persistent @hide="cerrarCaptura">
                <q-card class="tp-card" style="width:100%;max-width:520px">
                    <q-toolbar class="tp-header text-white">
                        <q-toolbar-title>Capturar evidencia</q-toolbar-title>
                        <q-btn flat round dense icon="close" @click="cerrarCaptura" />
                    </q-toolbar>
                    <q-card-section class="q-gutter-y-sm">
                        <q-banner v-if="camaraError" dense rounded class="bg-orange-1 text-orange-9">
                            <template #avatar><q-icon name="videocam_off" color="orange-9" /></template>
                            {{ camaraError }}
                        </q-banner>

                        <q-select outlined dense v-model="tipoEvidencia" :options="tiposCapturables"
                                  emit-value map-options label="Tipo de evidencia" color="teal-6" />

                        <!-- Vista de cámara / preview -->
                        <div v-show="!fotoTomada && streamActivo" class="tp-cam-wrap">
                            <video id="tp-video" playsinline muted class="tp-media"></video>
                        </div>
                        <canvas id="tp-canvas" class="tp-media" v-show="fotoTomada"></canvas>

                        <div v-if="!fotoTomada && streamActivo" class="text-center">
                            <q-btn round color="teal-6" icon="camera" size="lg" @click="capturarFrame" />
                            <div class="text-caption tp-muted q-mt-xs">Sólo cámara en vivo (no galería)</div>
                        </div>

                        <template v-if="fotoTomada">
                            <q-input v-if="tipoSel && tipoSel.es_lectura_dbm" outlined dense type="number"
                                     v-model.number="dbmValor" label="Lectura dBm" suffix="dBm" color="teal-6"
                                     hint="Rango válido: -60 a 10" />
                            <q-input v-if="tipoSel && tipoSel.requiere_justificacion" outlined dense
                                     v-model="justificacionValor" label="Justificación" type="textarea" autogrow color="teal-6" />
                            <div class="row q-gutter-sm">
                                <q-btn flat color="grey-7" icon="refresh" label="Repetir" @click="reintentarFoto" />
                                <q-space />
                                <q-btn unelevated color="teal-6" icon="cloud_upload" label="Enviar"
                                       :loading="enviandoEvidencia" @click="enviarEvidencia" />
                            </div>
                        </template>
                    </q-card-section>
                </q-card>
            </q-dialog>

            <!-- Diálogo de firma (signature-pad) -->
            <q-dialog v-model="firmaAbierta" persistent>
                <q-card class="tp-card" style="width:100%;max-width:520px">
                    <q-toolbar class="tp-header text-white">
                        <q-toolbar-title>Firma del {{ firmaSigner==='technician' ? 'técnico' : 'cliente' }}</q-toolbar-title>
                        <q-btn flat round dense icon="close" v-close-popup />
                    </q-toolbar>
                    <q-card-section>
                        <div class="tp-sig-wrap">
                            <canvas id="tp-sig" class="tp-sig"
                                    @pointerdown="sigStart" @pointermove="sigMove" @pointerup="sigEnd" @pointerleave="sigEnd"></canvas>
                        </div>
                        <div class="text-caption tp-muted q-mt-xs">Firma con el dedo dentro del recuadro.</div>
                        <div class="row q-gutter-sm q-mt-sm">
                            <q-btn flat color="grey-7" icon="clear" label="Limpiar" @click="limpiarFirma" />
                            <q-space />
                            <q-btn unelevated color="teal-6" icon="save" label="Guardar firma" :loading="guardandoFirma" @click="guardarFirma" />
                        </div>
                    </q-card-section>
                </q-card>
            </q-dialog>

            <q-footer class="tp-footer">
                <q-tabs v-model="tab" no-caps active-color="teal-6" indicator-color="teal-6" class="tp-footer" @update:model-value="onTabChange">
                    <q-tab name="inicio"    icon="today"        label="Mi día" />
                    <q-tab name="dinero"    icon="payments"     label="Mi dinero" />
                    <q-tab name="proyectos" icon="account_tree" label="Proyectos" />
                    <q-tab name="perfil"    icon="person"       label="Perfil" />
                </q-tabs>
            </q-footer>
        </q-layout>`,
    };

    const app = createApp(App);
    app.use(Quasar, {
        config: {
            brand: {
                primary: '#0d9488', secondary: '#0f766e',
                positive: '#16a34a', negative: '#dc2626',
                warning: '#d97706', info: '#0284c7',
            },
        },
        plugins: { Dialog: Quasar.Dialog, Notify: Quasar.Notify },
    });
    app.mount('#q-app');
})();
