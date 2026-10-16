/**
 * Pestañas del panel "Terminal" de DevTools = ventanas de la sesión tmux que
 * ttyd tiene enganchada (cada usuario cae siempre en su `web-<login_user>`,
 * ver ~/.bashrc). Aquí vive todo lo que habla con tmux; el componente solo pinta.
 *
 * CÓMO SE LEE LA LISTA: tmux le manda a la terminal un título (OSC 0/2) y
 * xterm.js lo entrega en `term.onTitleChange`. Se activa SOLO en la sesión
 * actual —`set-titles` y `set-titles-string` son opciones de sesión— con un
 * bucle `#{W:…}` que lista todas las ventanas. No se lee la barra de estado
 * porque recorta la lista cuando no cabe (`<…>`) y, con `status-left-length 10`,
 * pega el nombre de la sesión a la primera ventana. Tampoco se cambia
 * `window-status-format`: es opción de VENTANA, no de sesión, así que cada
 * `C-b c` nueva tomaría la global y perdería el marcador.
 *
 * ⚠️ CARRERA MEDIDA (2026-10-16, tmux 3.3a): `C-b :`, `C-b '` y `C-b &` abren
 * su prompt DESPUÉS de procesar todo lo que llegó en el mismo envío. Si el texto
 * viaja pegado a la tecla que abre el prompt, se PIERDE (8 de 8 intentos) y el
 * prompt se queda abierto esperando: la siguiente tecla del usuario cae ahí.
 * Con `C-b & y` la ventana no se cierra, y la "y" que el usuario teclee después
 * —por ejemplo dentro de Claude Code— la cierra. Por eso todo comando con texto
 * espera a VER el prompt dibujado en la última fila antes de mandar el texto, y
 * si no lo ve no manda nada.
 */

export const PREFIX = "\x02"; // C-b — verificado con `tmux show -gv prefix`

const MARK = "MGTABS1";
const FIELD = "~";
const SEP = "|";

// `|` y `~` son nuestros separadores: se quitan de los nombres antes de emitirlos.
const clean = (v) => `#{s/[|~]/_/:${v}}`;
const entry = (activa) =>
    `#{window_id}${FIELD}#{window_index}${FIELD}${activa}${FIELD}` +
    `${clean("window_name")}${FIELD}${clean("pane_current_command")}${SEP}`;

// Dentro de #{W:a,b} la coma separa el formato de las ventanas normales (a) del
// de la ventana actual (b); por eso los campos van con "~" y nunca con comas.
export const TITLE_FORMAT = `${MARK}${SEP}#{W:${entry(0)},${entry(1)}}`;

const SETUP_COMMAND = `set set-titles on ; set set-titles-string '${TITLE_FORMAT}'`;

const WINDOW_ID = /^@\d+$/;

/**
 * Título de tmux → lista de ventanas, o null si el título no es nuestro.
 * Acepta también el `document.title` del iframe, al que ttyd le agrega " | <su título>".
 */
export function parseTitle(raw) {
    const title = String(raw ?? "").split(" | ")[0];
    if (!title.startsWith(MARK + SEP)) return null;
    const tabs = [];
    for (const chunk of title.slice(MARK.length + 1).split(SEP)) {
        if (!chunk) continue;
        const [id, index, activa, name = "", command = ""] = chunk.split(FIELD);
        if (!WINDOW_ID.test(id) || !/^\d+$/.test(index)) continue;
        tabs.push({ id, index: Number(index), active: activa === "1", name, command });
    }
    return tabs.length ? tabs : null;
}

// Última fila de la pantalla viva (baseY), no la que el usuario esté mirando
// con la rueda (viewportY): ahí dibuja tmux su barra y sus prompts.
function statusLine(term) {
    const buf = term?.buffer?.active;
    if (!buf || !term.rows) return null;
    return buf.getLine(buf.baseY + term.rows - 1) || null;
}

/**
 * ¿Está tmux dibujando su barra normal? Fondo colour236 (status-style del
 * ~/.tmux.conf) y arranca con la sesión " web-…" (la crea ~/.bashrc). Si algo
 * no cuadra, NO se manda ninguna tecla: mejor barra deshabilitada que escribir
 * dentro de bash o de Claude.
 */
export function tmuxStatusVisible(term) {
    try {
        const line = statusLine(term);
        if (!line || !line.translateToString(true).startsWith(" web-")) return false;
        const cell = line.getCell(0);
        return !!cell && cell.isBgPalette() && cell.getBgColor() === 236;
    } catch (_) {
        return false;
    }
}

// Prompt de comandos de tmux abierto: la última fila arranca con ":".
function commandPromptOpen(term) {
    try {
        const line = statusLine(term);
        return !!line && line.translateToString(true).startsWith(":");
    } catch (_) {
        return false;
    }
}

// Mismo camino que una tecla real: dispara onData → websocket de ttyd.
function sendKeys(term, data) {
    try {
        if (typeof term?.input === "function") {
            term.input(data, true);
            return true;
        }
        const core = term?._core?.coreService;
        if (core && typeof core.triggerDataEvent === "function") {
            core.triggerDataEvent(data, true);
            return true;
        }
    } catch (_) {}
    return false;
}

function waitFor(term, predicate, timeoutMs) {
    return new Promise((resolve) => {
        if (predicate(term)) return resolve(true);
        let done = false;
        let sub = null;
        let poll = null;
        const finish = (ok) => {
            if (done) return;
            done = true;
            try { sub?.dispose(); } catch (_) {}
            clearInterval(poll);
            clearTimeout(timer);
            resolve(ok);
        };
        const check = () => { if (predicate(term)) finish(true); };
        const timer = setTimeout(() => finish(false), timeoutMs);
        try {
            sub = term.onWriteParsed(check);
        } catch (_) {
            poll = setInterval(check, 50);
        }
    });
}

/** Corre un comando de tmux por `C-b :`, esperando a ver el prompt antes de escribirlo. */
async function runCommand(term, command, timeoutMs = 1500) {
    if (!tmuxStatusVisible(term)) return false;
    if (!sendKeys(term, PREFIX + ":")) return false;
    if (!(await waitFor(term, commandPromptOpen, timeoutMs))) return false;
    return sendKeys(term, command + "\r");
}

// Las teclas de un solo golpe (prefijo + c, prefijo + dígito) no abren prompt:
// no hay carrera posible.
function singleKey(term, key) {
    if (!tmuxStatusVisible(term)) return false;
    return sendKeys(term, PREFIX + key);
}

export const newWindow = (term) => singleKey(term, "c");

export function selectWindow(term, tab) {
    if (!tab || !WINDOW_ID.test(tab.id) || !Number.isInteger(tab.index)) return false;
    if (tab.index <= 9) return singleKey(term, String(tab.index));
    return runCommand(term, `select-window -t ${tab.id}`);
}

export function killWindow(term, tab) {
    if (!tab || !WINDOW_ID.test(tab.id)) return Promise.resolve(false);
    return runCommand(term, `kill-window -t ${tab.id}`);
}

/**
 * Engancha las pestañas a un xterm (`iframe.contentWindow.term`).
 * `onChange({ tabs, ready, tmux })` se llama cuando cambia algo; `dispose()` suelta todo.
 */
export function createTmuxTabs(term, { onChange, initialTitle = "", debounceMs = 300 } = {}) {
    const state = { tabs: [], ready: false, tmux: false };
    const disposables = [];
    const timers = new Set();
    const startedAt = Date.now();
    let setupAttempts = 0;
    let setupRunning = false;
    let titleTimer = null;
    let statusTimer = null;
    let disposed = false;

    const emit = () => { if (!disposed) onChange?.({ ...state, tabs: state.tabs.slice() }); };
    const later = (fn, ms) => {
        const t = setTimeout(() => { timers.delete(t); fn(); }, ms);
        timers.add(t);
        return t;
    };

    const applyTitle = (raw) => {
        const tabs = parseTitle(raw);
        if (!tabs) return;
        state.tabs = tabs;
        state.ready = true;
        emit();
    };

    // Si la sesión aún no manda el título, se activa una vez (dos intentos como
    // máximo por enganche). Queda puesto en el servidor tmux: un F5 o una segunda
    // pestaña del navegador ya lo reciben al engancharse, sin repetir el comando.
    const maybeSetup = () => {
        if (state.ready || setupRunning || setupAttempts >= 2 || !state.tmux) return;
        if (Date.now() - startedAt < 1200) return; // deja llegar el título del enganche
        setupAttempts++;
        setupRunning = true;
        runCommand(term, SETUP_COMMAND).finally(() => {
            setupRunning = false;
            if (!state.ready) later(checkStatus, 4000);
        });
    };

    const checkStatus = () => {
        if (disposed) return;
        const visible = tmuxStatusVisible(term);
        if (visible !== state.tmux) {
            state.tmux = visible;
            emit();
        }
        maybeSetup();
    };

    try {
        disposables.push(term.onTitleChange((t) => {
            clearTimeout(titleTimer);
            titleTimer = setTimeout(() => applyTitle(t), debounceMs);
        }));
        disposables.push(term.onWriteParsed(() => {
            clearTimeout(statusTimer);
            statusTimer = setTimeout(checkStatus, debounceMs);
        }));
    } catch (_) {}

    applyTitle(initialTitle);
    later(checkStatus, 1500);

    const guarded = (fn) => (...args) => (state.tmux ? fn(term, ...args) : false);

    return {
        newWindow: guarded(newWindow),
        selectWindow: guarded(selectWindow),
        killWindow: guarded(killWindow),
        refresh: checkStatus,
        dispose() {
            disposed = true;
            clearTimeout(titleTimer);
            clearTimeout(statusTimer);
            timers.forEach(clearTimeout);
            timers.clear();
            disposables.forEach((d) => { try { d.dispose(); } catch (_) {} });
        },
    };
}
