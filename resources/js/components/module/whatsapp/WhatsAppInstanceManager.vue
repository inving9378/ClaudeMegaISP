<template>
    <div class="wim-shell tc-wrap" :class="{ 'tc-dark': darkMode }">
        <div v-if="fakeMode" class="wim-note">
            <i class="bi bi-cone-striped"></i>
            Modo desarrollo — Evolution API simulada. El QR mostrado es un placeholder.
        </div>

        <!-- #9991240: sin esto, que Evolution no exista en el servidor solo se notaba al pedir el QR. -->
        <div v-if="mostrarAvisoSalud" class="wim-health" role="alert">
            <i class="bi bi-exclamation-octagon-fill wim-health-icon"></i>
            <div class="wim-health-text">
                <strong>{{ saludTexto.titulo }}</strong>
                <div>{{ saludTexto.explicacion }}</div>
                <details class="wim-health-detail">
                    <summary>Detalle técnico</summary>
                    <code>{{ salud.url || 'sin URL' }}</code> — {{ salud.detalle }}
                </details>
            </div>
            <button class="tc-btn tc-btn-bad" @click="loadSalud(true)" :disabled="saludCargando">
                <i class="bi bi-arrow-clockwise" :class="{ 'wim-spin': saludCargando }"></i> Revisar de nuevo
            </button>
        </div>

        <div class="wim-header">
            <div class="wim-header-text">
                <h3 class="wim-title"><i class="bi bi-whatsapp"></i> Líneas WhatsApp</h3>
                <p v-if="instances.length" class="wim-subtitle">
                    {{ resumenLineas }}
                    <span v-if="catalog.some(f => f.exclusive)" class="wim-legend">
                        · <i class="bi bi-lock-fill"></i> = función exclusiva (solo puede estar en una línea)
                    </span>
                </p>
            </div>
            <button class="tc-btn tc-btn-ok wim-btn-lg" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Nueva línea
            </button>
        </div>

        <div v-if="loading" class="tc-card wim-empty">Cargando líneas…</div>
        <div v-else-if="!instances.length" class="tc-card wim-empty">
            <i class="bi bi-whatsapp wim-empty-icon"></i>
            <div class="wim-empty-title">Todavía no hay líneas</div>
            <div class="tc-muted-txt">Crea la primera para vincular un número de WhatsApp.</div>
        </div>

        <div v-else class="wim-grid">
            <article v-for="ins in instances" :key="ins.id" class="tc-card wim-card">
                <header class="wim-card-head">
                    <span class="wim-avatar" :class="statusClass(ins.status)" aria-hidden="true">
                        <i class="bi bi-whatsapp"></i>
                    </span>
                    <div class="wim-ident">
                        <div class="wim-name" :title="ins.name">{{ ins.name }}</div>
                        <div class="wim-phone">
                            <i class="bi bi-telephone"></i>
                            {{ ins.phone_number || 'Sin número vinculado' }}
                        </div>
                    </div>
                    <span class="tc-status" :class="statusTone(ins.status)">{{ statusLabel(ins.status) }}</span>
                </header>

                <div v-if="isProduction(ins)" class="wim-prod">
                    <i class="bi bi-shield-exclamation"></i>
                    <span><b>Línea de producción</b> · la usan ventas, el bot y la conciliación</span>
                </div>

                <div class="wim-card-body">
                    <dl class="wim-kv">
                        <dt>Identificador</dt>
                        <dd><code>{{ ins.slug }}</code></dd>
                        <template v-if="ins.instance_id && ins.instance_id !== ins.slug">
                            <dt>Etiqueta</dt>
                            <dd><code>{{ ins.instance_id }}</code></dd>
                        </template>
                        <dt>Proveedor</dt>
                        <dd>
                            <select
                                class="form-select form-select-sm tc-select wim-provider-select"
                                :value="ins.provider || 'evolution'"
                                @change="changeProvider(ins, $event.target.value)"
                                :disabled="providerBusy === ins.id"
                                aria-label="Proveedor de la línea"
                            >
                                <option value="evolution">Evolution API</option>
                                <option value="meta">WhatsApp oficial (Meta)</option>
                            </select>
                        </dd>
                    </dl>
                    <div v-if="ins.default_instance" class="wim-pill">
                        <i class="bi bi-star-fill"></i> Línea por defecto
                    </div>
                    <p v-if="(ins.provider || 'evolution') === 'meta'" class="wim-provider-hint">
                        <i class="bi bi-info-circle"></i>
                        <span>
                            Entra y sale por Meta; credenciales en <a href="/integraciones" target="_blank">Integraciones</a>.
                            El QR de Evolution aquí es <b>solo de consulta</b>.
                        </span>
                    </p>
                </div>

                <section v-if="catalog.length" class="wim-functions">
                    <div class="wim-functions-head">
                        <span class="wim-functions-title">Funciones</span>
                        <span class="wim-functions-count">{{ (ins.functions || []).length }} de {{ catalog.length }} activas</span>
                    </div>
                    <div class="wim-func-list">
                        <button
                            v-for="fn in catalog" :key="fn.id"
                            class="wim-func"
                            :class="{ on: hasFunction(ins, fn.id) }"
                            :aria-pressed="hasFunction(ins, fn.id) ? 'true' : 'false'"
                            :disabled="funcBusy"
                            @click="toggleFunction(ins, fn)"
                            :title="fn.exclusive ? 'Exclusiva: solo puede estar en una línea' : 'Compartida: puede estar en varias líneas'"
                        >
                            <i class="bi" :class="hasFunction(ins, fn.id) ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                            <span>{{ fn.name }}</span>
                            <i v-if="fn.exclusive" class="bi bi-lock-fill wim-func-lock" aria-label="exclusiva"></i>
                        </button>
                    </div>
                </section>

                <footer class="wim-card-actions">
                    <button class="tc-btn tc-btn-ok" @click="showQr(ins)" title="Mostrar el QR para vincular el teléfono">
                        <i class="bi bi-qr-code"></i>
                        {{ ins.status === 'connected' ? 'Reconectar' : 'Conectar' }}
                    </button>
                    <button
                        class="tc-btn"
                        @click="syncStatus(ins)"
                        :disabled="syncing[ins.id]"
                        title="Refrescar el estado real desde Evolution API"
                    >
                        <i class="bi bi-arrow-clockwise" :class="{ 'wim-spin': syncing[ins.id] }"></i>
                        {{ syncing[ins.id] ? 'Sincronizando…' : 'Sincronizar' }}
                    </button>
                    <button
                        v-if="ins.status === 'connected'"
                        class="tc-btn tc-btn-warn"
                        @click="askDisconnect(ins)"
                        title="Cerrar la sesión de WhatsApp en Evolution (logout). Se reconecta por QR."
                    >
                        <i class="bi bi-power"></i> Desconectar
                    </button>
                    <button
                        class="tc-btn wim-btn-icon"
                        :class="{ 'tc-btn-bad': !isProduction(ins) }"
                        @click="remove(ins)"
                        :title="isProduction(ins) ? 'Línea de producción — eliminar con precaución' : 'Eliminar línea'"
                        :aria-label="isProduction(ins) ? 'Eliminar línea de producción' : 'Eliminar línea'"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </footer>
            </article>
        </div>

        <!-- Modal crear línea -->
        <div v-if="showCreate" class="wim-modal-backdrop" @click.self="showCreate = false">
            <div class="wim-modal">
                <h4>Nueva línea WhatsApp</h4>
                <div class="wim-form">
                    <label>Nombre <input v-model="form.name" @input="autoSlug" placeholder="Ventas, Soporte..." /></label>
                    <label>Slug (URL) <input v-model="form.slug" placeholder="ventas-mx" /></label>
                    <label>Etiqueta / ID interno (opcional) <input v-model="form.instance_id" placeholder="Se usa el slug si lo dejas vacío" /></label>
                    <label>Número WhatsApp (display) <input v-model="form.phone_number" placeholder="+52 55 1234 5678" /></label>
                    <label class="wim-checkbox">
                        <input type="checkbox" v-model="form.default_instance" />
                        Marcar como línea por defecto
                    </label>
                </div>
                <div v-if="formError" class="wim-error">{{ formError }}</div>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="showCreate = false">Cancelar</button>
                    <button class="tc-btn tc-btn-ok" @click="create" :disabled="saving">
                        {{ saving ? 'Guardando…' : 'Crear' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal QR -->
        <div v-if="qrInstance" class="wim-modal-backdrop" @click.self="closeQr">
            <div class="wim-modal qr">
                <h4>Pareo WhatsApp — {{ qrInstance.name }}</h4>
                <div v-if="qrLoading" class="wim-empty">Solicitando QR…</div>
                <div v-else-if="qrError" class="wim-error">{{ qrError }}</div>
                <div v-else-if="qrImage" class="wim-qr-block">
                    <img :src="qrImageSrc" alt="QR WhatsApp" class="wim-qr-image" />
                    <div class="wim-qr-meta">
                        <div>Expira en: <strong>{{ qrCountdown }}s</strong></div>
                        <div v-if="qrPairingCode">
                            Código de pareo:
                            <code class="wim-pairing">{{ qrPairingCode }}</code>
                        </div>
                        <div class="wim-qr-status">
                            Estado: <strong>{{ statusLabel(qrInstance.status) }}</strong>
                        </div>
                    </div>
                    <p class="wim-qr-help">
                        Abre WhatsApp en tu teléfono → Menú → Dispositivos vinculados → Vincular un dispositivo.
                    </p>
                </div>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="closeQr">Cerrar</button>
                    <button class="tc-btn tc-btn-ok" @click="refreshQr">
                        <i class="bi bi-arrow-clockwise"></i> Refrescar QR
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: mover función exclusiva -->
        <div v-if="moveModal" class="wim-modal-backdrop" @click.self="moveModal = null">
            <div class="wim-modal">
                <h4>Mover función exclusiva</h4>
                <p class="wim-modal-text">
                    «<strong>{{ moveModal.fn.name }}</strong>» es exclusiva y hoy está en
                    «<strong>{{ moveModal.fromName }}</strong>». ¿Moverla a
                    «<strong>{{ moveModal.to.name }}</strong>»? Se quitará de la otra línea.
                </p>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="moveModal = null">Cancelar</button>
                    <button class="tc-btn tc-btn-ok" @click="confirmMove" :disabled="funcBusy">Mover aquí</button>
                </div>
            </div>
        </div>

        <!-- Modal: confirmar que la función quede sin línea (no bloqueante) -->
        <div v-if="orphanModal" class="wim-modal-backdrop" @click.self="orphanModal = null">
            <div class="wim-modal">
                <h4>Quitar la última línea</h4>
                <p class="wim-modal-text">
                    «<strong>{{ orphanModal.fn.name }}</strong>» quedará <strong>sin ninguna línea</strong>
                    atendiéndola. ¿Continuar?
                </p>
                <div v-if="otherLines(orphanModal.from).length" class="wim-orphan-alt">
                    <div class="wim-orphan-alt-title">o muévela a otra línea:</div>
                    <label class="wim-reassign-label">
                        <select v-model="reassignTarget">
                            <option :value="null" disabled>Elige una línea…</option>
                            <option v-for="l in otherLines(orphanModal.from)" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </label>
                    <div v-if="reassignError" class="wim-error">{{ reassignError }}</div>
                    <button class="tc-btn" :disabled="!reassignTarget || funcBusy" @click="confirmReassignFromOrphan">
                        Mover en vez de quitar
                    </button>
                </div>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="orphanModal = null">Cancelar</button>
                    <button class="tc-btn tc-btn-bad-solid" @click="confirmOrphan" :disabled="funcBusy">Quitar de todos modos</button>
                </div>
            </div>
        </div>

        <!-- Modal AVISO (no bloqueante): eliminar línea que es dueña única de funciones -->
        <div v-if="warnDeleteModal" class="wim-modal-backdrop" @click.self="warnDeleteModal = null">
            <div class="wim-modal">
                <h4>Estas funciones quedarán sin línea</h4>
                <p class="wim-modal-text">
                    Al eliminar «<strong>{{ warnDeleteModal.ins.name }}</strong>», estas funciones quedarán
                    <strong>sin ninguna línea</strong>: <strong>{{ warnDeleteModal.functions.join(', ') }}</strong>.
                    <template v-if="isProduction(warnDeleteModal.ins)"><br>⚠️ Además es el número de <strong>PRODUCCIÓN</strong>.</template>
                    ¿Eliminar de todos modos?
                </p>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="warnDeleteModal = null">Cancelar</button>
                    <button class="tc-btn tc-btn-bad-solid" @click="confirmWarnDelete" :disabled="funcBusy">Eliminar de todos modos</button>
                </div>
            </div>
        </div>

        <!-- Modal FUERTE: desconectar (logout real). Producción exige escribir el nombre. -->
        <div v-if="disconnectModal" class="wim-modal-backdrop" @click.self="disconnectModal = null">
            <div class="wim-modal">
                <h4>⚠️ Desconectar sesión de WhatsApp</h4>
                <p class="wim-modal-text">
                    Vas a <strong>cerrar la sesión</strong> de «<strong>{{ disconnectModal.ins.name }}</strong>»
                    ({{ disconnectModal.ins.phone_number || disconnectModal.ins.instance_id }}) en Evolution.
                    <template v-if="isProduction(disconnectModal.ins)">
                        <br><br><strong>Dejarán de llegar comprobantes y el bot dejará de responder</strong>
                        hasta que se reconecte escaneando el QR de nuevo.
                    </template>
                    <template v-else>
                        <br>La línea quedará desconectada y se reconecta por QR.
                    </template>
                </p>
                <div v-if="isProduction(disconnectModal.ins)" class="wim-confirm-type">
                    <div>Para confirmar, escribe el nombre de la instancia: <code>{{ disconnectModal.ins.instance_id }}</code></div>
                    <input v-model="disconnectModal.typed" placeholder="Escribe el nombre exacto" />
                </div>
                <div v-if="disconnectError" class="wim-error">{{ disconnectError }}</div>
                <div class="wim-modal-actions">
                    <button class="tc-btn" @click="disconnectModal = null">Cancelar</button>
                    <button
                        class="tc-btn tc-btn-bad-solid"
                        :disabled="funcBusy || (isProduction(disconnectModal.ins) && disconnectModal.typed !== disconnectModal.ins.instance_id)"
                        @click="confirmDisconnect"
                    >Desconectar</button>
                </div>
            </div>
        </div>

        <div v-if="funcToast" class="wim-toast" :class="funcToast.type">{{ funcToast.msg }}</div>
    </div>
</template>

<script>
import axios from 'axios';
import { darkMode } from '../../../hook/appConfig.js';

export default {
    name: 'WhatsAppInstanceManager',

    props: {
        fakeMode: { type: Boolean, default: false },
    },

    // Tema compartido de la Torre (.tc-wrap / .tc-dark), igual que Vendedores y Talento.
    setup() {
        return { darkMode };
    },

    data() {
        return {
            instances: [],
            loading: false,
            showCreate: false,
            saving: false,
            syncing: {},        // { instanceId: true } mientras Sincronizar está en curso
            formError: '',
            // Funciones por línea (Fase 3)
            catalog: [],        // funciones activas para los checks
            funcBusy: false,
            providerBusy: null, // id de la instancia mientras se cambia su proveedor
            moveModal: null,    // { fn, to, fromName }
            orphanModal: null,  // { fn, from } — confirmar que la función quede sin línea
            reassignTarget: null,
            reassignError: '',
            warnDeleteModal: null, // { ins, functions:[names] } — aviso, NO bloqueo
            disconnectModal: null, // { ins, typed } — logout real (producción exige teclear nombre)
            disconnectError: '',
            funcToast: null,
            salud: null,          // #9991240 — {ok, estado, url, detalle} de /whatsapp/api/evolution/salud
            saludCargando: false,
            form: {
                name: '',
                slug: '',
                instance_id: '',
                phone_number: '',
                default_instance: false,
            },

            qrInstance: null,
            qrLoading: false,
            qrError: '',
            qrImage: '',
            qrPairingCode: '',
            qrExpiresAt: null,
            qrCountdown: 0,
            qrCountdownInterval: null,
            qrStatusInterval: null,
        };
    },

    computed: {
        resumenLineas() {
            const total = this.instances.length;
            const conectadas = this.instances.filter(i => i.status === 'connected').length;
            return `${total} ${total === 1 ? 'línea' : 'líneas'} · ${conectadas} ${conectadas === 1 ? 'conectada' : 'conectadas'}`;
        },
        // Solo importa si alguna línea usa Evolution (o aún no hay líneas: la nueva nace en Evolution).
        mostrarAvisoSalud() {
            if (!this.salud || this.salud.ok) return false;
            return !this.instances.length
                || this.instances.some(i => (i.provider || 'evolution') === 'evolution');
        },
        saludTexto() {
            return {
                no_responde: {
                    titulo: 'Evolution API no está instalada o no responde en este servidor',
                    explicacion: 'Las líneas que usan Evolution no podrán mostrar el QR ni enviar o recibir mensajes hasta que se instale o se levante en el servidor.',
                },
                llave_rechazada: {
                    titulo: 'Evolution API responde, pero rechaza la llave de acceso',
                    explicacion: 'La llave de Evolution configurada en el servidor (WHATSAPP_API_KEY) no coincide con la de Evolution.',
                },
                sin_configurar: {
                    titulo: 'Evolution API no está configurada en este servidor',
                    explicacion: 'Falta la dirección de Evolution (WHATSAPP_API_URL) en la configuración del servidor.',
                },
            }[this.salud?.estado] || {
                titulo: 'Evolution API respondió con un error',
                explicacion: 'Las líneas que usan Evolution pueden fallar al conectar o al enviar mensajes.',
            };
        },
        // Mapa function_id -> [instance ids que la tienen], desde las funciones cargadas.
        ownership() {
            const map = {};
            this.instances.forEach(ins => (ins.functions || []).forEach(f => {
                (map[f.id] = map[f.id] || []).push(ins.id);
            }));
            return map;
        },
        qrImageSrc() {
            if (!this.qrImage) return '';
            if (this.qrImage.startsWith('data:image')) return this.qrImage;
            if (this.qrImage.startsWith('http')) return this.qrImage;
            // Si es solo base64 puro
            return 'data:image/png;base64,' + this.qrImage;
        },
    },

    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/whatsapp/api/instances');
                this.instances = Array.isArray(data) ? data : [];
            } catch (e) {
                this.instances = [];
            } finally {
                this.loading = false;
            }
        },

        // ── Funciones por línea (Fase 3) ─────────────────────────────────────────
        async loadCatalog() {
            try {
                const { data } = await axios.get('/whatsapp/api/instances/functions-catalog');
                this.catalog = Array.isArray(data) ? data : [];
            } catch (e) {
                this.catalog = [];
            }
        },
        hasFunction(ins, fnId) {
            return (ins.functions || []).some(f => f.id === fnId);
        },
        otherLines(ins) {
            return this.instances.filter(i => i.id !== ins.id);
        },
        soleOwnedFunctions(ins) {
            return (ins.functions || [])
                .filter(f => (this.ownership[f.id] || []).length <= 1)
                .map(f => f.name);
        },
        funcErr(e) {
            return e.response?.data?.message
                || Object.values(e.response?.data?.errors || {}).flat().join(', ')
                || 'No se pudo completar la operación.';
        },
        showFuncToast(msg, type, ms = 3800) {
            this.funcToast = { msg, type };
            setTimeout(() => { this.funcToast = null; }, ms);
        },
        async loadSalud(fresco = false) {
            this.saludCargando = true;
            try {
                const { data } = await axios.get('/whatsapp/api/evolution/salud', { params: fresco ? { fresco: 1 } : {} });
                this.salud = data;
            } catch (e) {
                this.salud = null; // si el chequeo mismo falla, no se inventa un diagnóstico
            } finally {
                this.saludCargando = false;
            }
        },
        // Item: migración a la API oficial de Meta, configurable por línea.
        // Cambia SOLO el proveedor de esta línea -- no toca sus funciones
        // asignadas ni ninguna otra configuración. Confirmación extra si es
        // la línea de producción (mismo criterio que desconectar/eliminar).
        async changeProvider(ins, newProvider) {
            if (newProvider === (ins.provider || 'evolution')) return;

            if (this.isProduction(ins)) {
                const ok = confirm(
                    `«${ins.name}» es la línea de PRODUCCIÓN (ventas/bot/conciliación). ` +
                    `¿Confirmas cambiar su proveedor a "${newProvider === 'meta' ? 'WhatsApp oficial' : 'Evolution API'}"?`
                );
                if (!ok) {
                    this.$forceUpdate(); // revierte el <select> a su valor real
                    return;
                }
            }

            this.providerBusy = ins.id;
            try {
                await axios.patch(`/whatsapp/api/instances/${ins.id}`, { provider: newProvider });
                await this.load();
            } catch (e) {
                alert(e.response?.data?.message ?? 'No se pudo cambiar el proveedor.');
            } finally {
                this.providerBusy = null;
            }
        },
        async toggleFunction(ins, fn) {
            if (this.funcBusy) return;
            if (this.hasFunction(ins, fn.id)) {
                // Quitar: si es la única línea → CONFIRMAR que la función quede sin línea (no bloqueo).
                if ((this.ownership[fn.id] || []).length <= 1) {
                    this.reassignTarget = null;
                    this.reassignError = '';
                    this.orphanModal = { fn, from: ins };
                    return;
                }
                await this.doUnassign(ins, fn);
            } else {
                // Asignar: exclusiva ya en otra línea → confirmar el movimiento.
                if (fn.exclusive) {
                    const otherId = (this.ownership[fn.id] || []).find(id => id !== ins.id);
                    if (otherId) {
                        const fromName = (this.instances.find(i => i.id === otherId) || {}).name || 'otra línea';
                        this.moveModal = { fn, to: ins, fromName };
                        return;
                    }
                }
                await this.doAssign(ins, fn);
            }
        },
        async doAssign(ins, fn) {
            this.funcBusy = true;
            try {
                const { data } = await axios.post(`/whatsapp/api/instances/${ins.id}/functions`, { function_id: fn.id });
                await this.load();
                if (data.moved) this.showFuncToast(`«${fn.name}» se movió a «${ins.name}»`, 'ok');
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
            }
        },
        async doUnassign(ins, fn) {
            this.funcBusy = true;
            try {
                await axios.delete(`/whatsapp/api/instances/${ins.id}/functions/${fn.id}`);
                await this.load();
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
            }
        },
        async confirmMove() {
            const { fn, to } = this.moveModal;
            this.moveModal = null;
            await this.doAssign(to, fn);
        },
        // Confirmar dejar la función sin línea (regla relajada).
        async confirmOrphan() {
            const { fn, from } = this.orphanModal;
            this.orphanModal = null;
            await this.doUnassign(from, fn);
        },
        // Opción secundaria del modal huérfana: mover a otra línea en vez de quitar.
        async confirmReassignFromOrphan() {
            if (!this.reassignTarget) return;
            const { fn, from } = this.orphanModal;
            this.funcBusy = true;
            this.reassignError = '';
            try {
                await axios.post(`/whatsapp/api/instances/${from.id}/functions/${fn.id}/reassign`, { to_instance_id: this.reassignTarget });
                this.orphanModal = null;
                await this.load();
                this.showFuncToast(`«${fn.name}» reasignada`, 'ok');
            } catch (e) {
                this.reassignError = this.funcErr(e);
            } finally {
                this.funcBusy = false;
            }
        },
        // Desconectar (logout real en Evolution) — distinto de Eliminar (fila local).
        askDisconnect(ins) {
            if (this.isProduction(ins)) {
                this.disconnectError = '';
                this.disconnectModal = { ins, typed: '' };
                return;
            }
            if (!confirm(`¿Desconectar «${ins.name}»? Cierra la sesión de WhatsApp; se reconecta por QR.`)) return;
            this.doDisconnect(ins);
        },
        async confirmDisconnect() {
            const ins = this.disconnectModal.ins;
            if (this.isProduction(ins) && this.disconnectModal.typed !== ins.instance_id) return;
            await this.doDisconnect(ins);
        },
        async doDisconnect(ins) {
            this.funcBusy = true;
            this.disconnectError = '';
            try {
                const { data } = await axios.post(`/whatsapp/api/instances/${ins.id}/disconnect`);
                this.disconnectModal = null;
                await this.load();
                this.showFuncToast(`«${ins.name}» desconectada — reconecta por QR`, 'ok');
            } catch (e) {
                this.disconnectError = this.funcErr(e);
                if (!this.disconnectModal) this.showFuncToast(this.funcErr(e), 'error');
            } finally {
                this.funcBusy = false;
            }
        },

        statusClass(status) {
            return {
                connected: 'connected',
                qr_pending: 'pending',
                disconnected: 'disconnected',
            }[status] || 'disconnected';
        },

        // Variante de .tc-status del tema compartido.
        statusTone(status) {
            return { connected: 'is-ok', qr_pending: 'is-warn' }[status] || 'is-bad';
        },

        statusLabel(status) {
            return {
                connected: 'Conectado',
                qr_pending: 'Esperando QR',
                disconnected: 'Desconectado',
            }[status] || 'Desconectado';
        },

        openCreate() {
            this.formError = '';
            this.form = {
                name: '',
                slug: '',
                instance_id: '',
                phone_number: '',
                default_instance: false,
            };
            this.showCreate = true;
        },

        autoSlug() {
            this.form.slug = this.form.name
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },

        async create() {
            this.formError = '';
            this.saving = true;
            try {
                const { data } = await axios.post('/whatsapp/api/instances', this.form);
                this.showCreate = false;
                await this.load();
                if (data?.evolution && !data.evolution.ok) {
                    this.showFuncToast(data.evolution.mensaje, 'warn', 10000);
                    this.loadSalud(true);
                }
            } catch (e) {
                this.formError = e.response?.data?.message
                    || Object.values(e.response?.data?.errors || {}).flat().join(', ')
                    || 'Error al crear la línea';
            } finally {
                this.saving = false;
            }
        },

        async syncStatus(ins, silencioso = false) {
            this.syncing = { ...this.syncing, [ins.id]: true };
            try {
                const { data } = await axios.get(`/whatsapp/api/instances/${ins.id}/status`);
                // Actualizar inline sin recargar la lista entera
                ins.status = data.status;
            } catch (e) {
                if (!silencioso) alert('No se pudo sincronizar: ' + (e.response?.data?.message || e.message));
            } finally {
                this.syncing = { ...this.syncing, [ins.id]: false };
            }
        },

        // La instancia de PRODUCCIÓN (meganet-ventas) es la que usan ventas, el bot y la
        // conciliación. Se identifica por slug/instance_id — se protege visualmente y con
        // una confirmación reforzada para que nadie la borre por reflejo.
        isProduction(ins) {
            return ins.slug === 'meganet-ventas' || ins.instance_id === 'meganet-ventas';
        },

        async remove(ins) {
            // Regla relajada: si es la única línea de funciones → AVISO (no bloqueo).
            const sole = this.soleOwnedFunctions(ins);
            if (sole.length) {
                this.warnDeleteModal = { ins, functions: sole };
                return;
            }
            const msg = this.isProduction(ins)
                ? `⚠️ Esto afecta el WhatsApp que usan conciliación y el bot.\n\n"${ins.name}" es el número de PRODUCCIÓN. Eliminarlo del panel quita su registro (no toca Evolution, pero perderías su seguimiento aquí).\n\n¿Seguro que quieres continuar?`
                : `¿Eliminar la línea "${ins.name}"?`;
            if (!confirm(msg)) return;
            await this.proceedDelete(ins);
        },
        async confirmWarnDelete() {
            const ins = this.warnDeleteModal.ins;
            this.warnDeleteModal = null;
            await this.proceedDelete(ins);
        },
        async proceedDelete(ins) {
            try {
                await axios.delete(`/whatsapp/api/instances/${ins.id}`);
                await this.load();
            } catch (e) {
                this.showFuncToast(this.funcErr(e), 'error');
            }
        },

        // Refleja el estado real (open/close) de cada instancia consultando Evolution,
        // sin esperar el clic manual en "Sincronizar".
        autoSyncAll() {
            this.instances.forEach(ins => this.syncStatus(ins, true));
        },

        async showQr(ins) {
            this.qrInstance = ins;
            this.qrError = '';
            this.qrImage = '';
            this.qrPairingCode = '';
            await this.fetchQr();
            this.startQrPolling();
        },

        async fetchQr() {
            this.qrLoading = true;
            try {
                const { data } = await axios.get(`/whatsapp/api/instances/${this.qrInstance.id}/qr`);
                this.qrImage = data.qrcode || data.base64 || data.code || '';
                this.qrPairingCode = data.pairingCode || '';
                this.qrExpiresAt = Date.now() + 120000;
                this.startCountdown();
            } catch (e) {
                this.qrError = 'No se pudo obtener el QR: ' + (e.response?.data?.message || e.message);
            } finally {
                this.qrLoading = false;
            }
        },

        async refreshQr() {
            await this.fetchQr();
        },

        startCountdown() {
            clearInterval(this.qrCountdownInterval);
            this.qrCountdownInterval = setInterval(() => {
                const left = Math.max(0, Math.round((this.qrExpiresAt - Date.now()) / 1000));
                this.qrCountdown = left;
                if (left === 0) clearInterval(this.qrCountdownInterval);
            }, 1000);
        },

        startQrPolling() {
            clearInterval(this.qrStatusInterval);
            this.qrStatusInterval = setInterval(async () => {
                if (!this.qrInstance) return;
                try {
                    const { data } = await axios.get(`/whatsapp/api/instances/${this.qrInstance.id}/status`);
                    this.qrInstance.status = data.status;
                    const localIns = this.instances.find(i => i.id === this.qrInstance.id);
                    if (localIns) localIns.status = data.status;
                    if (data.status === 'connected') {
                        clearInterval(this.qrStatusInterval);
                        setTimeout(() => this.closeQr(), 1500);
                    }
                } catch {}
            }, 3000);
        },

        closeQr() {
            clearInterval(this.qrCountdownInterval);
            clearInterval(this.qrStatusInterval);
            this.qrInstance = null;
            this.qrImage = '';
            this.load();
        },
    },

    async mounted() {
        await this.load();
        this.loadCatalog();
        await this.loadSalud();
        // Con Evolution caída cada sync automático fallaría (antes: una ventana de error por línea).
        if (!this.salud || this.salud.ok) this.autoSyncAll();
    },

    beforeUnmount() {
        clearInterval(this.qrCountdownInterval);
        clearInterval(this.qrStatusInterval);
    },
};
</script>

<style scoped>
/* Tema compartido de la Torre (.tc-wrap, resources/sass/module/vendors/_torre-theme.scss):
   tarjetas, botones, selector y pastillas de estado salen de ahí; aquí solo va el acomodo propio
   de Líneas, siempre con los tokens --tc-* para que claro y oscuro salgan solos. */
.wim-shell {
    --wim-brand: #25d366; /* verde de marca WhatsApp — solo para el ícono del título */
    /* Tinte neutro para fondos secundarios: --tc-bg2 es azulado y en oscuro choca con la
       tarjeta gris (#1a1a1a) del tema. Este gris translúcido sirve igual en claro y oscuro. */
    --wim-tint: rgba(100, 116, 139, 0.10);
    padding: 20px;
    max-width: 1280px;
    margin: 0 auto;
}

/* ── Encabezado ── */
.wim-header {
    display: flex; justify-content: space-between; align-items: flex-end;
    flex-wrap: wrap; gap: 12px;
    margin-bottom: 18px;
}
.wim-header-text { min-width: 0; }
.wim-title {
    margin: 0;
    font-size: 22px; font-weight: 700;
    color: var(--tc-ink);
    display: flex; align-items: center; gap: 10px;
}
.wim-title .bi { color: var(--wim-brand); }
.wim-subtitle { margin: 4px 0 0; font-size: 13px; color: var(--tc-muted); }
.wim-legend .bi { font-size: 11px; }
.wim-btn-lg { padding: 8px 16px; font-size: 14px; }

/* ── Avisos de página ── */
.wim-note {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 14px; margin-bottom: 16px;
    border-radius: 10px;
    background: rgba(217, 119, 6, 0.14);
    color: var(--tc-warn);
    font-size: 13px; font-weight: 600;
}
.wim-health {
    display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap;
    padding: 14px 16px; margin-bottom: 18px;
    border-radius: 14px;
    border: 1px solid var(--tc-bad);
    background: rgba(220, 38, 38, 0.07);
    color: var(--tc-ink);
    font-size: 13px;
}
.wim-health-icon { color: var(--tc-bad); font-size: 20px; flex-shrink: 0; line-height: 1.2; }
.wim-health-text { flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.wim-health-text > div { color: var(--tc-muted); }
.wim-health-detail { font-size: 12px; color: var(--tc-muted); overflow-wrap: anywhere; }
.wim-health-detail summary { cursor: pointer; }
.wim-health-detail code { background: var(--wim-tint); color: var(--tc-ink); padding: 1px 5px; border-radius: 4px; }

/* ── Estado vacío ── */
.wim-empty {
    padding: 40px 20px;
    text-align: center;
    color: var(--tc-muted);
    display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.wim-empty-icon { font-size: 34px; color: var(--wim-brand); }
.wim-empty-title { font-size: 15px; font-weight: 600; color: var(--tc-ink); }

/* ── Rejilla y tarjeta ── */
.wim-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr));
    gap: 16px;
    align-items: stretch;
}
.wim-grid .wim-card {
    margin: 0;
    min-width: 0;
    display: flex; flex-direction: column;
}

.wim-card-head {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 16px 12px;
}
.wim-avatar {
    flex-shrink: 0;
    width: 42px; height: 42px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px;
    background: rgba(100, 116, 139, 0.14); color: var(--tc-slate);
}
.wim-avatar.connected { background: rgba(22, 163, 74, 0.14); color: var(--tc-ok); }
.wim-avatar.pending   { background: rgba(217, 119, 6, 0.16); color: var(--tc-warn); }
.wim-avatar.disconnected { background: rgba(220, 38, 38, 0.10); color: var(--tc-bad); }
.wim-ident { flex: 1 1 auto; min-width: 0; }
.wim-name {
    font-size: 15px; font-weight: 700; color: var(--tc-ink);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.wim-phone {
    font-size: 12.5px; color: var(--tc-muted); margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.wim-phone .bi { font-size: 11px; margin-right: 2px; }
.wim-card-head .tc-status { flex-shrink: 0; white-space: nowrap; }
/* Tarjetas angostas (teléfono): el estado baja a su propio renglón, alineado con el nombre,
   para que el nombre y el número no se corten. */
@media (max-width: 520px) {
    .wim-card-head { flex-wrap: wrap; row-gap: 8px; }
    /* ícono (42) + separación (12) + nombre = renglón completo → el estado no cabe y baja. */
    .wim-card-head .wim-ident { flex: 1 1 calc(100% - 54px); }
    .wim-card-head .tc-status { order: 3; margin-left: 54px; }
}

.wim-prod {
    display: flex; align-items: center; gap: 8px;
    margin: 0 16px 4px;
    padding: 7px 10px;
    border-radius: 10px;
    background: rgba(217, 119, 6, 0.14);
    color: var(--tc-warn);
    font-size: 12.5px;
}
.wim-prod b { font-weight: 700; }

.wim-card-body { padding: 8px 16px 14px; }
.wim-kv {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    gap: 8px 14px;
    align-items: center;
    margin: 0;
    font-size: 13px;
}
.wim-kv dt { margin: 0; font-weight: 500; color: var(--tc-muted); }
.wim-kv dd { margin: 0; min-width: 0; color: var(--tc-ink); }
.wim-kv code {
    font-size: 12px;
    padding: 2px 7px;
    border-radius: 6px;
    background: var(--wim-tint);
    color: var(--tc-ink);
    overflow-wrap: anywhere;
}
.wim-provider-select { width: auto; max-width: 100%; min-width: 0; font-size: 13px; }

.wim-pill {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 10px;
    padding: 3px 10px;
    border-radius: 30px;
    background: rgba(37, 99, 235, 0.12);
    color: var(--tc-info);
    font-size: 12px; font-weight: 600;
}
.wim-provider-hint {
    display: flex; gap: 6px; align-items: flex-start;
    margin: 10px 0 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: var(--wim-tint);
    color: var(--tc-muted);
    font-size: 12px; line-height: 1.45;
}
.wim-provider-hint .bi { flex-shrink: 0; margin-top: 1px; color: var(--tc-info); }
.wim-provider-hint a { color: var(--tc-info); }

/* ── Funciones ── */
.wim-functions {
    padding: 12px 16px 14px;
    border-top: 1px solid var(--tc-line);
}
.wim-functions-head {
    display: flex; justify-content: space-between; align-items: baseline; gap: 8px;
    margin-bottom: 8px;
}
.wim-functions-title {
    font-size: 11.5px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--tc-muted);
}
.wim-functions-count { font-size: 12px; color: var(--tc-muted); }
.wim-func-list { display: flex; flex-wrap: wrap; gap: 6px; }
.wim-func {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 11px;
    border-radius: 30px;
    border: 1px solid var(--tc-line);
    background: transparent;
    color: var(--tc-ink);
    font-size: 12.5px; font-weight: 500;
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.wim-func .bi { font-size: 13px; color: var(--tc-muted); }
.wim-func:hover { border-color: var(--tc-accent); }
.wim-func.on {
    background: rgba(13, 148, 136, 0.12);
    border-color: var(--tc-accent);
    color: var(--tc-accent);
    font-weight: 600;
}
.wim-func.on .bi { color: var(--tc-accent); }
.wim-func .wim-func-lock { font-size: 10.5px; opacity: .8; }
.wim-func:disabled { opacity: .6; cursor: wait; }

/* ── Acciones (siempre al pie, aunque las tarjetas tengan alturas distintas) ── */
.wim-card-actions {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-top: auto;
    padding: 12px 16px;
    border-top: 1px solid var(--tc-line);
    background: var(--wim-tint);
}
.wim-card-actions .tc-btn { white-space: nowrap; }
/* En oscuro el acento del tema es un turquesa claro: el texto blanco casi no se lee. */
.wim-shell.tc-dark .tc-btn-ok,
.wim-shell.tc-dark .tc-btn-ok:hover { color: #042f2e; }
.wim-card-actions .wim-btn-icon { margin-left: auto; padding: 6px 10px; }

/* ── Modales ── */
.wim-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, .55);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
}
.wim-modal {
    background: var(--tc-surface);
    color: var(--tc-ink);
    border: 1px solid var(--tc-line);
    border-radius: 16px;
    padding: 22px;
    max-width: 480px; width: 92%;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
}
.wim-modal h4 { margin: 0 0 6px; font-size: 17px; font-weight: 700; color: var(--tc-ink); }
.wim-modal.qr { max-width: 420px; text-align: center; }
.wim-modal-text { color: var(--tc-ink); font-size: 13.5px; line-height: 1.5; }
.wim-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.wim-form { display: flex; flex-direction: column; gap: 12px; margin: 14px 0; }
.wim-form label {
    display: flex; flex-direction: column; gap: 5px;
    font-size: 12.5px; font-weight: 500; color: var(--tc-muted);
}
.wim-form input,
.wim-reassign-label select,
.wim-confirm-type input {
    background: var(--tc-surface);
    color: var(--tc-ink);
    border: 1px solid var(--tc-line);
    border-radius: 9px;
    padding: 8px 11px;
    font-size: 13px;
}
.wim-form input:focus,
.wim-reassign-label select:focus,
.wim-confirm-type input:focus { outline: none; border-color: var(--tc-accent); }
.wim-checkbox { flex-direction: row !important; align-items: center; gap: 8px; color: var(--tc-ink) !important; }
.wim-checkbox input { width: auto; }
.wim-error {
    background: rgba(220, 38, 38, 0.08);
    color: var(--tc-bad);
    padding: 8px 12px;
    border-radius: 9px;
    font-size: 12.5px;
    margin-bottom: 10px;
}
.wim-reassign-label { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--tc-muted); margin: 12px 0; }
.wim-orphan-alt {
    margin: 12px 0; padding: 12px;
    border-radius: 12px;
    background: var(--wim-tint); border: 1px solid var(--tc-line);
}
.wim-orphan-alt-title { font-size: 12px; color: var(--tc-muted); margin-bottom: 6px; }
.wim-confirm-type { margin: 12px 0; font-size: 12.5px; color: var(--tc-muted); }
.wim-confirm-type code { background: var(--wim-tint); color: var(--tc-ink); padding: 1px 6px; border-radius: 4px; }
.wim-confirm-type input { display: block; width: 100%; margin-top: 6px; border-color: var(--tc-bad); }

/* ── QR ── */
.wim-qr-block { display: flex; flex-direction: column; align-items: center; gap: 14px; margin: 16px 0; }
.wim-qr-image { width: 256px; height: 256px; background: #fff; padding: 8px; border-radius: 12px; }
.wim-qr-meta { text-align: center; color: var(--tc-muted); font-size: 13px; }
.wim-qr-meta div { margin: 4px 0; }
.wim-qr-status { margin-top: 8px; }
.wim-pairing {
    background: var(--wim-tint); color: var(--tc-ink);
    padding: 4px 10px; border-radius: 6px;
    font-size: 16px; letter-spacing: 2px;
}
.wim-qr-help { font-size: 12px; color: var(--tc-muted); margin: 0; }

/* ── Toast ── */
.wim-toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    padding: 10px 18px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    z-index: 10001; max-width: 90%; text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
}
.wim-toast.ok { background: var(--tc-ok); color: #fff; }
.wim-toast.error { background: var(--tc-bad); color: #fff; }
.wim-toast.warn { background: var(--tc-warn); color: #fff; }

.wim-spin { display: inline-block; animation: wim-spin 1s linear infinite; }
@keyframes wim-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>
