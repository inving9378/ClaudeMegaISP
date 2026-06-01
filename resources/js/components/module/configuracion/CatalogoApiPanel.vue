<template>
    <div class="cap-wrap">

        <!-- Header -->
        <div class="cap-header">
            <div>
                <h5 class="cap-title">Catálogo de API</h5>
                <p class="cap-subtitle">{{ modules.length }} módulos · {{ allScopes.length }} scopes · {{ keys.length }} llaves activas</p>
            </div>
            <button class="cap-btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg me-1"></i>Nueva llave
            </button>
        </div>

        <!-- Tabs -->
        <div class="cap-tabs">
            <button :class="['cap-tab', { active: tab === 'catalog' }]" @click="tab = 'catalog'">
                <i class="bi bi-list-ul me-1"></i>Catálogo de endpoints
            </button>
            <button :class="['cap-tab', { active: tab === 'keys' }]" @click="tab = 'keys'; loadKeys()">
                <i class="bi bi-key me-1"></i>Llaves activas
            </button>
        </div>

        <!-- Tab: Catálogo -->
        <div v-if="tab === 'catalog'">
            <div v-if="loading" class="cap-loading">Cargando catálogo...</div>
            <template v-else>
                <div class="cap-filter-row">
                    <input class="cap-search" v-model="search" placeholder="Buscar endpoint o módulo..." />
                    <span class="cap-filter-count">{{ filteredModules.length }} módulos</span>
                </div>
                <div v-for="mod in filteredModules" :key="mod.slug" class="cap-module">
                    <div class="cap-module-header" @click="toggleModule(mod.slug)">
                        <span class="cap-module-name">{{ mod.name }}</span>
                        <span class="cap-module-meta">
                            {{ mod.endpoints.length }} endpoint{{ mod.endpoints.length !== 1 ? 's' : '' }}
                        </span>
                        <i :class="['bi', expandedModules.includes(mod.slug) ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
                    </div>
                    <div v-if="expandedModules.includes(mod.slug)" class="cap-endpoint-list">
                        <div v-for="(ep, i) in mod.endpoints" :key="i" class="cap-endpoint">
                            <span :class="['cap-method', `method-${ep.method?.toLowerCase()}`]">{{ ep.method }}</span>
                            <span class="cap-path">{{ ep.path }}</span>
                            <span v-if="ep.scope || ep.permission" class="cap-scope-tag">
                                {{ ep.scope || ep.permission }}
                            </span>
                            <span v-if="ep.description" class="cap-ep-desc">{{ ep.description }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Tab: Llaves -->
        <div v-if="tab === 'keys'">
            <div v-if="loadingKeys" class="cap-loading">Cargando llaves...</div>
            <div v-else-if="keys.length === 0" class="cap-empty">
                Sin llaves activas. Crea una con "Nueva llave".
            </div>
            <div v-else class="cap-keys-table">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Scopes</th>
                            <th>Último uso</th>
                            <th>Expira</th>
                            <th>Creada</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="key in keys" :key="key.id">
                            <td><strong>{{ key.name }}</strong><br><small class="text-muted">{{ key.owner }}</small></td>
                            <td>
                                <div class="cap-abilities">
                                    <span v-for="ab in key.abilities" :key="ab" class="cap-scope-tag">{{ ab }}</span>
                                </div>
                            </td>
                            <td>{{ key.last_used_at ? relTime(key.last_used_at) : 'Nunca' }}</td>
                            <td>{{ key.expires_at ?? 'Sin expiración' }}</td>
                            <td>{{ key.created_at?.substring(0, 10) }}</td>
                            <td>
                                <button class="cap-btn-danger" @click="revokeKey(key)" title="Revocar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: crear llave -->
        <div v-if="showCreateModal" class="cap-modal-backdrop" @click.self="showCreateModal = false">
            <div class="cap-modal">
                <div class="cap-modal-header">
                    <strong>Nueva llave de API</strong>
                    <button class="btn-close" @click="showCreateModal = false"></button>
                </div>
                <div class="cap-modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la llave *</label>
                        <input class="form-control" v-model="newKey.name" placeholder="ej: Integración CRM externo" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expiración (días, opcional)</label>
                        <input class="form-control" type="number" v-model="newKey.expires_in_days" min="1" max="3650" placeholder="Dejar vacío = sin expiración" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scopes habilitados *</label>
                        <p class="text-muted" style="font-size:12px;margin-bottom:8px;">Selecciona los módulos/permisos que esta llave puede usar</p>
                        <div class="cap-scope-selector">
                            <div v-for="mod in modules" :key="mod.slug" class="cap-scope-mod">
                                <div class="cap-scope-mod-title">
                                    <input type="checkbox"
                                        :checked="allModuleScopesSelected(mod)"
                                        :indeterminate.prop="someModuleScopesSelected(mod)"
                                        @change="toggleAllModuleScopes(mod)"
                                    />
                                    <strong>{{ mod.name }}</strong>
                                </div>
                                <div class="cap-scope-checks">
                                    <label v-for="scope in mod.scopes" :key="scope" class="cap-scope-check">
                                        <input type="checkbox" v-model="newKey.abilities" :value="scope" />
                                        {{ scope }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="cap-modal-footer">
                    <button class="btn btn-secondary me-2" @click="showCreateModal = false">Cancelar</button>
                    <button class="btn btn-primary" @click="createKey"
                            :disabled="creating || !newKey.name.trim() || newKey.abilities.length === 0">
                        {{ creating ? 'Creando...' : 'Generar llave' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: mostrar token generado (solo una vez) -->
        <div v-if="generatedToken" class="cap-modal-backdrop">
            <div class="cap-modal">
                <div class="cap-modal-header">
                    <strong>⚠️ Copia tu token ahora</strong>
                </div>
                <div class="cap-modal-body">
                    <div class="alert alert-warning">
                        Este token <strong>no se mostrará de nuevo</strong>. Cópialo y guárdalo en tu gestor de contraseñas.
                    </div>
                    <div class="cap-token-box">{{ generatedToken }}</div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" @click="copyToken">
                        <i class="bi bi-clipboard me-1"></i>Copiar
                    </button>
                </div>
                <div class="cap-modal-footer">
                    <button class="btn btn-primary" @click="generatedToken = null; tab = 'keys'; loadKeys()">
                        Listo, ya lo copié
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <transition name="cap-toast-fade">
            <div v-if="toast.visible" class="cap-toast" :class="`cap-toast-${toast.type}`">
                {{ toast.message }}
            </div>
        </transition>

    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'CatalogoApiPanel',
    setup() {
        const tab            = ref('catalog');
        const modules        = ref([]);
        const allScopes      = ref([]);
        const keys           = ref([]);
        const loading        = ref(true);
        const loadingKeys    = ref(false);
        const expandedModules= ref([]);
        const search         = ref('');
        const showCreateModal= ref(false);
        const creating       = ref(false);
        const generatedToken = ref(null);
        const toast          = ref({ visible: false, message: '', type: 'success', timer: null });
        const newKey         = ref({ name: '', abilities: [], expires_in_days: '' });

        const filteredModules = computed(() => {
            if (!search.value.trim()) return modules.value;
            const q = search.value.toLowerCase();
            return modules.value.filter(m =>
                m.name.toLowerCase().includes(q) ||
                m.endpoints.some(e => e.path?.toLowerCase().includes(q) || e.description?.toLowerCase().includes(q))
            );
        });

        async function loadCatalog() {
            loading.value = true;
            try {
                const { data } = await axios.get('/configuracion/api/catalogo/catalog');
                if (!data?.modules) {
                    showToast('Sin acceso al catálogo. Verifica permisos.', 'error');
                    return;
                }
                modules.value   = data.modules;
                allScopes.value = data.scopes ?? [];
                if (data.modules.length) expandedModules.value = [data.modules[0].slug];
            } catch (e) {
                showToast('Error al cargar catálogo.', 'error');
            } finally {
                loading.value = false;
            }
        }

        async function loadKeys() {
            loadingKeys.value = true;
            try {
                const { data } = await axios.get('/configuracion/api/catalogo/keys');
                keys.value = data.keys;
            } catch (e) {
                showToast('Error al cargar llaves.', 'error');
            } finally {
                loadingKeys.value = false;
            }
        }

        function toggleModule(slug) {
            const idx = expandedModules.value.indexOf(slug);
            if (idx === -1) expandedModules.value.push(slug);
            else expandedModules.value.splice(idx, 1);
        }

        function allModuleScopesSelected(mod) {
            return mod.scopes.every(s => newKey.value.abilities.includes(s));
        }

        function someModuleScopesSelected(mod) {
            const has = mod.scopes.filter(s => newKey.value.abilities.includes(s));
            return has.length > 0 && has.length < mod.scopes.length;
        }

        function toggleAllModuleScopes(mod) {
            if (allModuleScopesSelected(mod)) {
                newKey.value.abilities = newKey.value.abilities.filter(s => !mod.scopes.includes(s));
            } else {
                mod.scopes.forEach(s => {
                    if (!newKey.value.abilities.includes(s)) newKey.value.abilities.push(s);
                });
            }
        }

        function openCreateModal() {
            newKey.value = { name: '', abilities: [], expires_in_days: '' };
            showCreateModal.value = true;
        }

        async function createKey() {
            if (!newKey.value.name.trim() || newKey.value.abilities.length === 0) return;
            creating.value = true;
            try {
                const payload = {
                    name: newKey.value.name,
                    abilities: newKey.value.abilities,
                };
                if (newKey.value.expires_in_days) {
                    payload.expires_in_days = parseInt(newKey.value.expires_in_days);
                }
                const { data } = await axios.post('/configuracion/api/catalogo/keys', payload);
                generatedToken.value = data.plain_text;
                showCreateModal.value = false;
            } catch (e) {
                showToast(e.response?.data?.message || 'Error al crear llave.', 'error');
            } finally {
                creating.value = false;
            }
        }

        async function revokeKey(key) {
            if (!confirm(`¿Revocar la llave "${key.name}"? Esta acción no se puede deshacer.`)) return;
            try {
                await axios.delete(`/configuracion/api/catalogo/keys/${key.id}`);
                keys.value = keys.value.filter(k => k.id !== key.id);
                showToast('Llave revocada.', 'success');
            } catch (e) {
                showToast('Error al revocar.', 'error');
            }
        }

        async function copyToken() {
            try {
                await navigator.clipboard.writeText(generatedToken.value);
                showToast('Token copiado.', 'success');
            } catch {
                const ta = document.createElement('textarea');
                ta.value = generatedToken.value;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Token copiado (fallback).', 'success');
            }
        }

        function relTime(iso) {
            if (!iso) return '—';
            const diff = Date.now() - new Date(iso).getTime();
            const m = Math.floor(diff / 60000);
            if (m < 60) return `hace ${m} min`;
            const h = Math.floor(m / 60);
            if (h < 24) return `hace ${h} h`;
            return `hace ${Math.floor(h / 24)} días`;
        }

        function showToast(message, type = 'success') {
            if (toast.value.timer) clearTimeout(toast.value.timer);
            toast.value = { visible: true, message, type, timer: setTimeout(() => { toast.value.visible = false; }, 3500) };
        }

        onMounted(loadCatalog);

        return {
            tab, modules, allScopes, keys, loading, loadingKeys,
            expandedModules, search, filteredModules,
            showCreateModal, creating, generatedToken, newKey, toast,
            loadCatalog, loadKeys, toggleModule,
            allModuleScopesSelected, someModuleScopesSelected, toggleAllModuleScopes,
            openCreateModal, createKey, revokeKey, copyToken, relTime,
        };
    },
};
</script>

<style scoped>
.cap-wrap { padding: 20px 0; font-size: 14px; }

.cap-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.cap-title  { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.cap-subtitle { font-size: 12px; color: #6b7280; margin: 0; }

.cap-btn-primary { padding: 8px 18px; border-radius: 8px; background: #3b82f6; color: #fff; border: none; cursor: pointer; font-weight: 600; }
.cap-btn-primary:hover { background: #2563eb; }
.cap-btn-danger  { padding: 4px 10px; border-radius: 6px; background: transparent; border: 1px solid #fca5a5; color: #dc2626; cursor: pointer; }
.cap-btn-danger:hover { background: #fee2e2; }

.cap-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
.cap-tab  { padding: 8px 16px; border: none; background: transparent; cursor: pointer; font-size: 14px; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.cap-tab.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 600; }

.cap-filter-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.cap-search { flex: 1; border: 1px solid #d1d5db; border-radius: 8px; padding: 7px 12px; font-size: 13px; outline: none; }
.cap-search:focus { border-color: #3b82f6; }
.cap-filter-count { font-size: 12px; color: #9ca3af; white-space: nowrap; }

.cap-module { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
.cap-module-header { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; background: #f9fafb; }
.cap-module-header:hover { background: #f3f4f6; }
.cap-module-name { flex: 1; font-weight: 600; }
.cap-module-meta { font-size: 12px; color: #9ca3af; }

.cap-endpoint-list { padding: 8px 14px 12px; display: flex; flex-direction: column; gap: 6px; }
.cap-endpoint { display: flex; align-items: baseline; flex-wrap: wrap; gap: 8px; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
.cap-endpoint:last-child { border-bottom: none; }

.cap-method { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; flex-shrink: 0; }
.method-get    { background: #dbeafe; color: #1d4ed8; }
.method-post   { background: #dcfce7; color: #15803d; }
.method-patch  { background: #fef9c3; color: #a16207; }
.method-put    { background: #fef9c3; color: #a16207; }
.method-delete { background: #fee2e2; color: #dc2626; }

.cap-path { font-family: monospace; font-size: 13px; font-weight: 500; }
.cap-ep-desc { font-size: 12px; color: #6b7280; }
.cap-scope-tag { font-size: 10px; padding: 1px 7px; border-radius: 10px; background: #f3f4f6; color: #374151; font-weight: 600; flex-shrink: 0; }

.cap-loading { padding: 24px; color: #6b7280; font-size: 13px; }
.cap-empty   { padding: 24px; color: #9ca3af; font-size: 13px; text-align: center; }

/* Keys table */
.cap-keys-table { overflow-x: auto; }
.cap-keys-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
.cap-keys-table th { text-align: left; padding: 8px 12px; border-bottom: 2px solid #e5e7eb; font-size: 11px; text-transform: uppercase; color: #6b7280; }
.cap-keys-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
.cap-abilities { display: flex; flex-wrap: wrap; gap: 4px; }

/* Modal */
.cap-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 16px; }
.cap-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
.cap-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; }
.cap-modal-body   { padding: 20px; overflow-y: auto; flex: 1; }
.cap-modal-footer { padding: 14px 20px; border-top: 1px solid #e5e7eb; text-align: right; flex-shrink: 0; }

.cap-scope-selector { border: 1px solid #e5e7eb; border-radius: 8px; max-height: 280px; overflow-y: auto; padding: 8px; }
.cap-scope-mod { margin-bottom: 10px; }
.cap-scope-mod-title { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.cap-scope-checks { display: flex; flex-wrap: wrap; gap: 4px 16px; padding-left: 20px; }
.cap-scope-check { display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer; }

.cap-token-box { background: #1e1e2e; color: #a6e3a1; font-family: monospace; font-size: 12px; padding: 12px; border-radius: 8px; word-break: break-all; }

/* Toast */
.cap-toast { position: fixed; bottom: 28px; right: 28px; z-index: 9999; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); }
.cap-toast-success { background: #16a34a; color: #fff; }
.cap-toast-error   { background: #dc2626; color: #fff; }
.cap-toast-fade-enter-active, .cap-toast-fade-leave-active { transition: all .25s; }
.cap-toast-fade-enter-from, .cap-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>
