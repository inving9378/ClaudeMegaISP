<template>
    <div class="module-manager">
        <!-- KPI cards -->
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-cubes fa-2x text-secondary me-3"></i>
                            <div>
                                <div class="text-muted small">Total módulos</div>
                                <div class="h3 m-0">{{ stats.total }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-check-circle fa-2x text-success me-3"></i>
                            <div>
                                <div class="text-muted small">Migrados</div>
                                <div class="h3 m-0 text-success">{{ stats.migrated }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-clock fa-2x text-warning me-3"></i>
                            <div>
                                <div class="text-muted small">Pendientes</div>
                                <div class="h3 m-0 text-warning">{{ stats.pending }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-dollar-sign fa-2x text-primary me-3"></i>
                            <div>
                                <div class="text-muted small">Créditos usados</div>
                                <div class="h3 m-0">${{ stats.cost.toFixed(4) }} USD</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module table -->
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Módulos del sistema</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Módulo</th>
                                <th>Tipo</th>
                                <th>Migración</th>
                                <th>Estado</th>
                                <th>Créditos</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="m in modules" :key="m.slug">
                                <tr>
                                    <td class="chev-cell" @click="toggleExpand(m.slug)">
                                        <i class="fa" :class="expanded[m.slug] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ m.name }}</div>
                                        <div class="text-muted small">{{ m.slug }} · v{{ m.version }}</div>
                                    </td>
                                    <td>
                                        <span class="badge" :class="m.type === 'core' ? 'bg-secondary' : 'bg-info'">
                                            {{ m.type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="m.migrated ? 'bg-success' : 'bg-warning text-dark'">
                                            {{ m.migrated ? 'Migrado' : 'Pendiente' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="m.type === 'core'" class="text-muted" title="Los módulos core no se pueden desactivar">
                                            <i class="fa fa-lock"></i>
                                        </span>
                                        <q-toggle
                                            v-else
                                            :model-value="m.active"
                                            color="primary"
                                            :disable="togglingSlug === m.slug"
                                            @update:model-value="toggleActive(m)"
                                        />
                                    </td>
                                    <td>
                                        <div>${{ m.total_cost.toFixed(4) }}</div>
                                        <div class="text-muted small">{{ m.runs }} ejecucion(es)</div>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            v-if="!m.migrated"
                                            class="btn btn-sm btn-primary"
                                            :disabled="migratingSlug === m.slug"
                                            @click="migrate(m)"
                                        >
                                            <span v-if="migratingSlug === m.slug">
                                                <i class="fa fa-spinner fa-spin"></i>
                                                Procesando…
                                            </span>
                                            <span v-else>
                                                <i class="fa fa-play"></i>
                                                Migrar
                                            </span>
                                        </button>
                                        <span v-else class="text-success">
                                            <i class="fa fa-check-circle"></i>
                                            Hecho
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="expanded[m.slug]" class="expanded-row">
                                    <td></td>
                                    <td colspan="6">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Descripción:</strong>
                                                <p class="mb-1">{{ m.description || '—' }}</p>
                                                <div><strong>Archivos en el módulo:</strong> {{ m.files_count }}</div>
                                                <div><strong>Versión:</strong> {{ m.version }}</div>
                                                <div><strong>Slug:</strong> <code>{{ m.slug }}</code></div>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Historial:</strong>
                                                <div v-if="loadingHistory[m.slug]" class="text-muted small">Cargando…</div>
                                                <div v-else-if="!history[m.slug] || history[m.slug].length === 0" class="text-muted small">
                                                    Sin ejecuciones.
                                                </div>
                                                <table v-else class="table table-sm mt-1">
                                                    <thead>
                                                        <tr>
                                                            <th>Fecha</th>
                                                            <th>Estado</th>
                                                            <th>Tokens</th>
                                                            <th>Costo</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="h in history[m.slug]" :key="h.id">
                                                            <td>{{ formatDate(h.started_at) }}</td>
                                                            <td>
                                                                <span class="badge" :class="statusBadge(h.status)">
                                                                    {{ h.status }}
                                                                </span>
                                                            </td>
                                                            <td>{{ h.input_tokens }} / {{ h.output_tokens }}</td>
                                                            <td>${{ Number(h.cost_usd).toFixed(4) }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div v-if="lastPlan[m.slug]" class="mt-2">
                                            <strong>Última propuesta de Claude:</strong>
                                            <pre class="claude-plan">{{ lastPlan[m.slug] }}</pre>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ModuleManager',
    props: {
        initialModules: { type: Array, required: true },
        totalModules: { type: Number, required: true },
        migratedCount: { type: Number, required: true },
        pendingCount: { type: Number, required: true },
        totalCostUsd: { type: [Number, String], required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            modules: this.initialModules.map((m) => ({
                ...m,
                total_cost: Number(m.total_cost) || 0,
                runs: Number(m.runs) || 0,
            })),
            stats: {
                total: this.totalModules,
                migrated: this.migratedCount,
                pending: this.pendingCount,
                cost: Number(this.totalCostUsd) || 0,
            },
            expanded: {},
            history: {},
            loadingHistory: {},
            lastPlan: {},
            migratingSlug: null,
            togglingSlug: null,
        };
    },
    methods: {
        toggleExpand(slug) {
            this.expanded[slug] = !this.expanded[slug];
            if (this.expanded[slug] && this.history[slug] === undefined) {
                this.fetchHistory(slug);
            }
        },
        async fetchHistory(slug) {
            this.loadingHistory[slug] = true;
            try {
                const { data } = await axios.get(`/admin/modules/${slug}/history`);
                this.history[slug] = data.history || [];
            } catch (e) {
                this.history[slug] = [];
            } finally {
                this.loadingHistory[slug] = false;
            }
        },
        async migrate(m) {
            this.migratingSlug = m.slug;
            try {
                const { data } = await axios.post(`/admin/modules/${m.slug}/migrate`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (data.success) {
                    this.lastPlan[m.slug] = data.plan;
                    m.total_cost = Number(m.total_cost) + Number(data.cost_usd);
                    m.runs = Number(m.runs) + 1;
                    this.stats.cost += Number(data.cost_usd);
                    if (this.expanded[m.slug]) {
                        await this.fetchHistory(m.slug);
                    }
                    this.notify('Plan generado por Claude. Costo: $' + Number(data.cost_usd).toFixed(4), 'positive');
                } else {
                    this.notify(data.error || 'Falló la migración', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.migratingSlug = null;
            }
        },
        async toggleActive(m) {
            this.togglingSlug = m.slug;
            try {
                const { data } = await axios.post(`/admin/modules/${m.slug}/toggle`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (data.success) {
                    m.active = data.active;
                } else {
                    this.notify(data.error || 'No se pudo cambiar el estado', 'negative');
                }
            } catch (e) {
                this.notify(e.response?.data?.error || e.message, 'negative');
            } finally {
                this.togglingSlug = null;
            }
        },
        statusBadge(status) {
            return {
                completed: 'bg-success',
                running: 'bg-info',
                pending: 'bg-secondary',
                failed: 'bg-danger',
            }[status] || 'bg-secondary';
        },
        formatDate(ts) {
            if (!ts) return '—';
            return new Date(ts).toLocaleString('es-MX');
        },
        notify(message, type) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message, color: type === 'positive' ? 'green' : 'red', position: 'top-right', timeout: 3500 });
            } else {
                window.alert(message);
            }
        },
    },
};
</script>

<style scoped>
.module-manager .stat-card { border: 1px solid #eef0f4; }
.module-manager .chev-cell { cursor: pointer; width: 28px; color: #6c757d; }
.module-manager .expanded-row { background: #fafbfc; }
.module-manager .claude-plan {
    white-space: pre-wrap;
    background: #1e1e2e;
    color: #cdd6f4;
    padding: 1rem;
    border-radius: 6px;
    max-height: 320px;
    overflow-y: auto;
    font-size: 12px;
}
</style>
