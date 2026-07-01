/* Talento Portal Técnico — shell PWA (Quasar UMD + Vue 3).
 * Sub-paso 1: chrome de la app (header teal, nav inferior, tema oscuro por usuario,
 * registro del service worker). Las pantallas Mi día / OT+evidencia / Proyectos
 * se cablean en los sub-pasos 2–4 reutilizando /talento/api y sus servicios.
 * No forma parte del bundle del admin ni del interceptor spa-nav. */
(function () {
    'use strict';

    const CFG = window.__PORTAL__ || {};
    const { createApp, ref, computed, onMounted } = Vue;

    // ── Registro del service worker (solo en contexto seguro: https o localhost) ──
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

            // Aplica el tema inicial al plugin Dark de Quasar.
            $q.dark.set(dark.value);

            const nombre = computed(() =>
                colaborador.value && colaborador.value.nombre
                    ? colaborador.value.nombre
                    : 'Colaborador');

            const tipoLabel = computed(() => {
                const t = colaborador.value && colaborador.value.tipo;
                const map = { technician: 'Técnico', supervisor: 'Supervisor', ayudante: 'Ayudante' };
                return map[t] || (t || 'Técnico');
            });

            function toggleDark() {
                dark.value = !dark.value;
                const theme = dark.value ? 'dark' : 'light';
                $q.dark.set(dark.value);
                document.documentElement.classList.toggle('portal-dark', dark.value);
                document.querySelector('meta[name="theme-color"]')
                    ?.setAttribute('content', dark.value ? '#0f1620' : '#0d9488');
                // Sin-flash en la próxima carga + verdad por-usuario en servidor.
                try { localStorage.setItem('talentoPortalTheme', theme); } catch (e) {}
                savingTheme.value = true;
                fetch(CFG.endpoints.saveTheme, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CFG.csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ theme }),
                }).catch(() => {/* offline: queda en localStorage, se reintenta al reabrir */})
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

            onMounted(registerServiceWorker);

            return { colaborador, tab, dark, savingTheme, nombre, tipoLabel, toggleDark, logout };
        },

        template: `
        <q-layout view="hHh lpr fFf">
            <q-header elevated class="tp-header">
                <q-toolbar>
                    <q-avatar size="30px" color="white" text-color="teal-8">
                        <q-icon name="engineering" />
                    </q-avatar>
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

                        <q-card v-else flat bordered class="tp-card q-mb-md">
                            <q-card-section class="row items-center no-wrap">
                                <q-avatar color="teal-6" text-color="white" size="46px">
                                    <q-icon name="person" />
                                </q-avatar>
                                <div class="q-ml-md">
                                    <div class="text-subtitle1 text-weight-medium">Hola, {{ nombre }}</div>
                                    <div class="tp-muted text-caption">{{ tipoLabel }}</div>
                                </div>
                            </q-card-section>
                        </q-card>

                        <q-banner class="tp-card q-mb-md" rounded>
                            <template #avatar><q-icon name="rocket_launch" color="teal-6" /></template>
                            El shell del portal está listo. El <b>check-in con geocerca</b> y tus
                            <b>órdenes del día</b> se activan en la siguiente entrega.
                        </q-banner>
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
                primary: '#0d9488',
                secondary: '#0f766e',
                positive: '#16a34a',
                negative: '#dc2626',
                warning: '#d97706',
                info: '#0284c7',
            },
        },
        plugins: { Dialog: Quasar.Dialog, Notify: Quasar.Notify },
    });
    app.mount('#q-app');
})();
