const toastr = require("toastr");
window._ = require('lodash');
try {
    window.$ = window.jQuery = require('jquery');
} catch (e) {}

window.axios = require('axios');
window.toastr = toastr;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfMeta = document.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content;
}

// Refresca el token CSRF del <meta> y de axios.defaults con un token fresco.
// Se usa ante un 419 (token stale por login/logout en otra pestaña — fix 419
// multi-pestaña). Devuelve el token nuevo o null si el refresh falló.
window.refreshCsrfToken = async function () {
    try {
        // GET no puede dar 419; no recursa en la rama 419 de abajo.
        const { data } = await window.axios.get('/csrf-refresh', { _csrfRetried: true });
        const token = data && data.token;
        if (!token) return null;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        return token;
    } catch (e) {
        return null;
    }
};

// Red de seguridad para errores 5xx silenciosos (Hoja de Ruta #109, Paso 2).
// Cualquier 500/502/503... deja rastro en consola aunque el .catch local esté vacío.
// IMPORTANTE: este interceptor SOLO loguea. Re-lanza el error (Promise.reject) para
// no alterar el flujo: los .catch existentes siguen recibiendo el error igual que antes.
// NO muestra notificaciones visuales aquí — eso lo manejan los catches específicos de
// cada módulo (evita doble-toast). Como todos los módulos usan el mismo singleton axios
// (import axios from 'axios' === window.axios), basta registrarlo una vez aquí.
window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;

        // 419 = token CSRF stale (Capa 1a). Refresca el token y reintenta UNA vez.
        // Guard anti-bucle: si el request ya se reintentó, propaga el error.
        if (status === 419 && error.config && !error.config._csrfRetried) {
            error.config._csrfRetried = true;
            const token = await window.refreshCsrfToken();
            if (token) {
                // Sobreescribe el header del request fallido (cubre también los ~40
                // componentes que pasan su propio X-CSRF-TOKEN: this.csrfToken).
                error.config.headers = error.config.headers || {};
                error.config.headers['X-CSRF-TOKEN'] = token;
                return window.axios(error.config);
            }
        }

        if (status >= 500) {
            const cfg = error.config || {};
            const method = (cfg.method || 'get').toUpperCase();
            const url = cfg.url || '(url desconocida)';
            const backendMsg = error?.response?.data?.message || error?.response?.data?.error || '';
            console.error(
                `[axios 5xx] ${status} ${method} ${url}` + (backendMsg ? ` — ${backendMsg}` : '')
            );
        }
        return Promise.reject(error);
    }
);

window.drift = require('drift-zoom');
window.moment = require('moment');

// Fallback de portapapeles para contexto NO seguro (HTTP plano, ej. http://192.168.105.11).
// navigator.clipboard sólo existe en https/localhost; en HTTP plano queda undefined y todo
// navigator.clipboard.writeText(...) revienta. Aquí lo definimos con un writeText que cae a
// execCommand('copy') vía textarea temporal, así TODOS los componentes que ya usan
// navigator.clipboard.writeText funcionan en HTTP sin tocar cada uno. (Item Hoja de Ruta #16.)
(function () {
    const legacyWrite = (text) => new Promise((resolve, reject) => {
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-9999px';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            ok ? resolve() : reject(new Error('execCommand copy devolvió false'));
        } catch (e) {
            reject(e);
        }
    });

    // Sólo intervenimos si NO hay clipboard nativo o el contexto es inseguro (HTTP plano).
    if (!navigator.clipboard || !window.isSecureContext) {
        try {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: legacyWrite },
                configurable: true,
            });
        } catch (e) {
            // Algunos navegadores no permiten redefinir navigator.clipboard; los componentes
            // con su propio fallback (DevtoolsPanel, etc.) siguen funcionando.
        }
    }
})();
