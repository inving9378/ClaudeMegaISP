/* Talento Portal Técnico — shell PWA (Quasar UMD + Vue 3).
 * Sub-paso 1: chrome (header teal, nav inferior, tema por usuario, service worker).
 * Sub-paso 2: "Mi día" real — check-in/checkout geocercado (delega en AttendanceService).
 * Reutiliza /talento/portal/* que a su vez delega en la capa de servicios existente.
 * No forma parte del bundle del admin ni del interceptor spa-nav. */
(function () {
    'use strict';

    const CFG = window.__PORTAL__ || {};
    const { createApp, ref, computed, onMounted } = Vue;

    // ── HTTP helper (mismo origen, CSRF de sesión) ──
    async function apiFetch(url, options) {
        const opts = Object.assign({
            headers: {},
            credentials: 'same-origin',
        }, options || {});
        opts.headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CFG.csrf,
        }, opts.headers);
        if (opts.body && typeof opts.body !== 'string') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        const res = await fetch(url, opts);
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        return { ok: res.ok, status: res.status, data };
    }

    // ── Geolocalización como promesa (alta precisión) ──
    function getPosition() {
        return new Promise((resolve, reject) => {
            if (!('geolocation' in navigator)) {
                reject({ code: 'unsupported' });
                return;
            }
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

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        const secure = window.isSecureContext ||
            ['localhost', '127.0.0.1'].includes(location.hostname);
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

            // ── Estado de asistencia ──
            const asistencia = ref(null);      // { check_in_at, check_out_at, geocerca, flagged, flag_reason }
            const cargandoAsistencia = ref(true);
            const accionAsistencia = ref(false); // check-in/checkout en curso

            $q.dark.set(dark.value);

            const nombre = computed(() =>
                colaborador.value && colaborador.value.nombre ? colaborador.value.nombre : 'Colaborador');

            const tipoLabel = computed(() => {
                const t = colaborador.value && colaborador.value.tipo;
                const map = { technician: 'Técnico', supervisor: 'Supervisor', ayudante: 'Ayudante', interno: 'Interno' };
                return map[t] || (t || 'Técnico');
            });

            const yaEntro   = computed(() => !!(asistencia.value && asistencia.value.check_in_at));
            const yaSalio   = computed(() => !!(asistencia.value && asistencia.value.check_out_at));
            const turnoAbierto = computed(() => yaEntro.value && !yaSalio.value);

            async function cargarAsistencia() {
                if (!colaborador.value) { cargandoAsistencia.value = false; return; }
                cargandoAsistencia.value = true;
                const { ok, data } = await apiFetch('/talento/portal/asistencia/hoy');
                asistencia.value = (ok && data) ? data : null;
                cargandoAsistencia.value = false;
            }

            async function registrarEntrada() {
                if (accionAsistencia.value) return;
                accionAsistencia.value = true;
                let pos;
                try {
                    pos = await getPosition();
                } catch (err) {
                    accionAsistencia.value = false;
                    const msg = err && err.code === 1
                        ? 'Necesitamos tu ubicación para el check-in. Activa el permiso de ubicación e inténtalo de nuevo.'
                        : 'No pudimos obtener tu ubicación. Revisa el GPS e inténtalo de nuevo.';
                    $q.notify({ type: 'negative', message: msg, timeout: 5000 });
                    return;
                }
                const { latitude, longitude, accuracy } = pos.coords;
                const { ok, status, data } = await apiFetch('/talento/portal/asistencia/checkin', {
                    method: 'POST',
                    body: { lat: latitude, lng: longitude, accuracy: accuracy },
                });
                accionAsistencia.value = false;

                if (!ok) {
                    $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo registrar la entrada.' });
                    return;
                }
                await cargarAsistencia();

                if (data.status === 'already_open') {
                    $q.notify({ type: 'info', message: 'Ya tenías una entrada activa hoy.' });
                } else if (data.flagged) {
                    const acc = Math.round(accuracy || 0);
                    $q.notify({
                        type: 'warning',
                        icon: 'flag',
                        message: 'Registrado — marcado para revisión' + (data.flag_reason ? ' (' + data.flag_reason + ')' : ''),
                        caption: 'Precisión ~' + acc + ' m',
                        timeout: 6000,
                    });
                } else {
                    $q.notify({
                        type: 'positive',
                        icon: 'check_circle',
                        message: 'Entrada registrada' + (data.geocerca ? ' en ' + data.geocerca : ''),
                        timeout: 4000,
                    });
                }
            }

            async function registrarSalida() {
                if (accionAsistencia.value) return;
                accionAsistencia.value = true;
                // La salida no exige geocerca; mandamos coords si están disponibles, sin bloquear.
                let body = {};
                try {
                    const pos = await getPosition();
                    body = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                } catch (e) { /* salida sin coords: permitido */ }

                const { ok, data } = await apiFetch('/talento/portal/asistencia/checkout', {
                    method: 'POST', body,
                });
                accionAsistencia.value = false;

                if (!ok) {
                    $q.notify({ type: 'negative', message: (data && data.message) || 'No se pudo registrar la salida.' });
                    return;
                }
                await cargarAsistencia();
                $q.notify({ type: 'positive', icon: 'logout', message: 'Salida registrada. ¡Buen trabajo!', timeout: 4000 });
            }

            // ── Tema ──
            function toggleDark() {
                dark.value = !dark.value;
                const theme = dark.value ? 'dark' : 'light';
                $q.dark.set(dark.value);
                document.documentElement.classList.toggle('portal-dark', dark.value);
                document.querySelector('meta[name="theme-color"]')
                    ?.setAttribute('content', dark.value ? '#0f1620' : '#0d9488');
                try { localStorage.setItem('talentoPortalTheme', theme); } catch (e) {}
                savingTheme.value = true;
                apiFetch(CFG.endpoints.saveTheme, { method: 'POST', body: { theme } })
                    .catch(() => {})
                    .finally(() => { savingTheme.value = false; });
            }

            function logout() {
                $q.dialog({
                    title: 'Cerrar sesión',
                    message: '¿Salir del portal técnico?',
                    cancel: { label: 'Cancelar', flat: true },
                    ok: { label: 'Salir', color: 'negative', unelevated: true },
                    persistent: true,
                }).onOk(() => {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.action = CFG.endpoints.logout;
                    const t = document.createElement('input');
                    t.type = 'hidden'; t.name = '_token'; t.value = CFG.csrf;
                    f.appendChild(t);
                    document.body.appendChild(f);
                    f.submit();
                });
            }

            onMounted(() => {
                registerServiceWorker();
                cargarAsistencia();
            });

            return {
                colaborador, tab, dark, savingTheme, nombre, tipoLabel,
                asistencia, cargandoAsistencia, accionAsistencia,
                yaEntro, yaSalio, turnoAbierto, fmtHora,
                registrarEntrada, registrarSalida, toggleDark, logout,
            };
        },

        template: `
        <q-layout view="hHh lpr fFf">
            <q-header elevated class="tp-header">
                <q-toolbar>
                    <q-avatar size="30px" color="white" text-color="teal-8"><q-icon name="engineering" /></q-avatar>
                    <q-toolbar-title>
                        Talento Campo
                        <div class="tp-colab-chip" v-if="colaborador">{{ nombre }} · {{ tipoLabel }}</div>
                    </q-toolbar-title>
                    <q-btn flat round dense :icon="dark ? 'light_mode' : 'dark_mode'"
                           :loading="savingTheme" @click="toggleDark" aria-label="Cambiar tema" />
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

                            <!-- Tarjeta de asistencia -->
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
                                        <!-- Turno cerrado -->
                                        <div v-if="yaSalio" class="tp-muted">
                                            <q-icon name="task_alt" color="positive" /> Turno cerrado.
                                            Entrada {{ fmtHora(asistencia.check_in_at) }} · Salida {{ fmtHora(asistencia.check_out_at) }}.
                                        </div>

                                        <!-- Turno abierto -->
                                        <template v-else-if="turnoAbierto">
                                            <q-banner v-if="asistencia.stale_day" dense rounded
                                                      class="bg-orange-1 text-orange-9 q-mb-sm">
                                                <template #avatar><q-icon name="history_toggle_off" color="orange-9" /></template>
                                                Tienes un turno de un día anterior sin cerrar. Registra tu salida para poder iniciar uno nuevo.
                                            </q-banner>
                                            <div class="row items-center q-gutter-x-sm q-mb-sm">
                                                <q-chip dense color="positive" text-color="white" icon="login">
                                                    Entrada {{ fmtHora(asistencia.check_in_at) }}
                                                </q-chip>
                                                <q-chip v-if="asistencia.geocerca" dense outline color="teal-7" icon="place">
                                                    {{ asistencia.geocerca }}
                                                </q-chip>
                                                <q-chip v-if="asistencia.flagged" dense color="orange-8" text-color="white" icon="flag">
                                                    Marcado para revisión
                                                </q-chip>
                                            </div>
                                            <q-btn unelevated color="teal-7" icon="logout" label="Registrar salida"
                                                   :loading="accionAsistencia" @click="registrarSalida" class="full-width" />
                                        </template>

                                        <!-- Sin entrada -->
                                        <template v-else>
                                            <div class="tp-muted q-mb-sm">Aún no registras tu entrada.</div>
                                            <q-btn unelevated color="teal-6" icon="my_location" label="Registrar entrada"
                                                   :loading="accionAsistencia" @click="registrarEntrada" class="full-width" />
                                            <div class="text-caption tp-muted q-mt-sm">
                                                Usaremos tu ubicación para validar la geocerca en el servidor.
                                            </div>
                                        </template>
                                    </template>
                                </q-card-section>
                            </q-card>

                            <q-banner class="tp-card" rounded>
                                <template #avatar><q-icon name="assignment" color="teal-6" /></template>
                                Tus órdenes del día aparecerán aquí en la próxima entrega.
                            </q-banner>
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
                                    <q-item-section>
                                        <q-item-label>{{ nombre }}</q-item-label>
                                        <q-item-label caption>{{ tipoLabel }}</q-item-label>
                                    </q-item-section>
                                </q-item>
                                <q-item v-if="colaborador && colaborador.email">
                                    <q-item-section avatar><q-icon name="mail" color="teal-6" /></q-item-section>
                                    <q-item-section><q-item-label>{{ colaborador.email }}</q-item-label></q-item-section>
                                </q-item>
                                <q-item tag="label">
                                    <q-item-section avatar><q-icon name="dark_mode" color="teal-6" /></q-item-section>
                                    <q-item-section><q-item-label>Modo oscuro</q-item-label></q-item-section>
                                    <q-item-section side>
                                        <q-toggle :model-value="dark" @update:model-value="toggleDark" color="teal-6" />
                                    </q-item-section>
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
                <q-tabs v-model="tab" no-caps active-color="teal-6" indicator-color="teal-6" class="tp-footer">
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
