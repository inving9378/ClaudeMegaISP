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
            const otSeleccionada = ref(null); // {origen, id, ...} — navegación al detalle (Sub-paso 3)

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

            // Navegación al detalle (el detalle en sí lo implementa el Sub-paso 3).
            function abrirDetalle(ot) { otSeleccionada.value = ot; }
            function cerrarDetalle() { otSeleccionada.value = null; }

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

            return {
                colaborador, tab, dark, savingTheme, nombre, tipoLabel,
                asistencia, cargandoAsistencia, accionAsistencia, yaEntro, yaSalio, turnoAbierto,
                ots, cargandoOts, otSeleccionada,
                fmtHora, statusInfo,
                registrarEntrada, registrarSalida, abrirDetalle, cerrarDetalle, toggleDark, logout,
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

                        <!-- Detalle de OT (navegación cableada; contenido completo en Sub-paso 3) -->
                        <template v-else-if="otSeleccionada">
                            <q-card flat bordered class="tp-card q-mb-md">
                                <q-card-section>
                                    <div class="text-overline tp-muted">{{ otSeleccionada.tipo }}</div>
                                    <div class="text-h6">OT #{{ otSeleccionada.folio }}</div>
                                    <q-chip dense :color="statusInfo(otSeleccionada.status).color" text-color="white" class="q-mt-xs">
                                        {{ statusInfo(otSeleccionada.status).label }}
                                    </q-chip>
                                </q-card-section>
                                <q-separator />
                                <q-list>
                                    <q-item v-if="otSeleccionada.cliente">
                                        <q-item-section avatar><q-icon name="person" color="teal-6" /></q-item-section>
                                        <q-item-section><q-item-label caption>Cliente</q-item-label><q-item-label>{{ otSeleccionada.cliente }}</q-item-label></q-item-section>
                                    </q-item>
                                    <q-item v-if="otSeleccionada.direccion">
                                        <q-item-section avatar><q-icon name="place" color="teal-6" /></q-item-section>
                                        <q-item-section><q-item-label caption>Dirección</q-item-label><q-item-label>{{ otSeleccionada.direccion }}</q-item-label></q-item-section>
                                    </q-item>
                                    <q-item v-if="otSeleccionada.telefono">
                                        <q-item-section avatar><q-icon name="call" color="teal-6" /></q-item-section>
                                        <q-item-section><q-item-label caption>Teléfono</q-item-label><q-item-label>{{ otSeleccionada.telefono }}</q-item-label></q-item-section>
                                    </q-item>
                                </q-list>
                            </q-card>
                            <q-banner class="tp-card" rounded>
                                <template #avatar><q-icon name="build" color="teal-6" /></template>
                                El detalle completo con checklist de evidencia, captura anti-fraude y firma del
                                cliente llega en la próxima entrega.
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

            <q-footer class="tp-footer">
                <q-tabs v-model="tab" no-caps active-color="teal-6" indicator-color="teal-6" class="tp-footer" @update:model-value="cerrarDetalle">
                    <q-tab name="inicio"    icon="today"        label="Mi día" />
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
