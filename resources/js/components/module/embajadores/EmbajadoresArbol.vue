<template>
    <div class="embajadores-arbol">
        <div v-if="loading" class="text-center py-4 text-muted">
            <i class="fa fa-spinner fa-spin me-2"></i>Cargando árbol…
        </div>
        <div v-else-if="error" class="alert alert-danger py-2">{{ error }}</div>
        <div v-else-if="flatNodes.length === 0" class="text-muted small">Sin referidos aún.</div>
        <div v-else>
            <div class="mb-2 d-flex justify-content-between align-items-center">
                <span class="text-muted small">{{ flatNodes.length }} nodo(s) en el árbol</span>
                <button class="btn btn-sm btn-outline-secondary" @click="expandirTodo">
                    <i class="fa fa-expand-alt me-1"></i>Expandir todo
                </button>
            </div>
            <div class="arbol-lista">
                <template v-for="nodo in flatNodes" :key="nodo.id">
                    <div v-show="nodo._visible"
                         class="arbol-nodo d-flex align-items-center gap-2 py-1 px-2 rounded mb-1"
                         :style="{ paddingLeft: (nodo.depth * 24 + 8) + 'px' }">

                        <!-- Toggle colapsar/expandir -->
                        <button v-if="nodo._hasChildren" class="btn btn-xs btn-link text-muted p-0"
                                style="width:18px;min-width:18px;" @click="toggleNodo(nodo)">
                            <i class="fa" :class="nodo._open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                        </button>
                        <span v-else style="width:18px;min-width:18px;display:inline-block;"></span>

                        <!-- Ícono usuario -->
                        <i class="fa fa-user" :class="depthColor(nodo.depth)"></i>

                        <!-- Nombre -->
                        <span class="fw-600 flex-grow-1">{{ nodo.name }}</span>

                        <!-- Nivel -->
                        <span class="badge" :class="depthBadge(nodo.depth)" style="font-size:.7rem;">
                            {{ nodo.depth === 0 ? 'Raíz' : 'N' + nodo.depth }}
                        </span>

                        <!-- Plan -->
                        <span v-if="nodo.plan_type" class="badge bg-light text-secondary border" style="font-size:.65rem;">
                            {{ planLabel(nodo.plan_type) }}
                        </span>

                        <!-- Comisión total -->
                        <span class="text-success small fw-600" style="min-width:90px;text-align:right;">
                            ${{ formatMoney(nodo.commission_total) }}
                        </span>

                        <!-- Código -->
                        <code v-if="nodo.referral_code" class="small text-muted" style="font-size:.7rem;">
                            {{ nodo.referral_code }}
                        </code>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'EmbajadoresArbol',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
        clienteId: { type: Number, required: true },
    },
    data() {
        return {
            loading:   true,
            error:     null,
            flatNodes: [],
        };
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            this.loading = true;
            this.error   = null;
            try {
                const { data } = await axios.get(
                    `${this.baseUrl}/clientes/${this.clienteId}/tree`
                );
                this.flatNodes = this.aplanar(data, true);
            } catch (e) {
                this.error = 'Error al cargar el árbol.';
                console.error('EmbajadoresArbol:', e);
            } finally {
                this.loading = false;
            }
        },
        aplanar(nodo, visible, lista = []) {
            const hasChildren = (nodo.children || []).length > 0;
            const flat = {
                id:               nodo.id,
                name:             nodo.name,
                depth:            nodo.depth,
                plan_type:        nodo.plan_type,
                referral_code:    nodo.referral_code,
                commission_total: nodo.commission_total,
                _hasChildren:     hasChildren,
                _open:            true,
                _visible:         visible,
            };
            lista.push(flat);
            for (const child of nodo.children || []) {
                this.aplanar(child, visible, lista);
            }
            return lista;
        },
        toggleNodo(nodo) {
            nodo._open = !nodo._open;
            this.actualizarVisibilidad();
        },
        actualizarVisibilidad() {
            // Reconstruye visibilidad: un nodo es visible si todos sus ancestros están abiertos
            const openByDepthStack = [];
            for (const nodo of this.flatNodes) {
                // Truncar pila al nivel actual
                openByDepthStack.length = nodo.depth;
                nodo._visible = openByDepthStack.every(Boolean);
                openByDepthStack[nodo.depth] = nodo._open;
            }
        },
        expandirTodo() {
            for (const n of this.flatNodes) {
                n._open    = true;
                n._visible = true;
            }
        },
        depthColor(d) {
            return ['text-primary', 'text-success', 'text-warning', 'text-danger', 'text-secondary'][d] ?? 'text-muted';
        },
        depthBadge(d) {
            return ['bg-primary', 'bg-success', 'bg-warning text-dark', 'bg-danger', 'bg-secondary'][d] ?? 'bg-light text-dark';
        },
        planLabel(plan) {
            return plan === 'single_reward' ? 'Mensualidad' : plan === 'multilevel' ? 'Multinivel' : plan;
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
.btn-xs { padding: 0 3px; font-size: .75rem; }
.arbol-nodo { background: #f8f9fa; border: 1px solid #eef0f4; transition: background .15s; }
.arbol-nodo:hover { background: #e9ecef; }
.arbol-lista { max-height: 480px; overflow-y: auto; }
</style>
