<template>
    <div class="voip-grupos">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="fa fa-users me-2 text-primary"></i>Grupos de timbrado
            </h4>
            <button v-if="canCreate" class="btn btn-primary btn-sm" @click="abrirModal()">
                <i class="fa fa-plus me-1"></i> Nuevo grupo
            </button>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-body p-0">
                <div v-if="cargando" class="text-center py-5 text-muted">
                    <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
                </div>
                <div v-else-if="grupos.length === 0" class="text-center py-5 text-muted">
                    <i class="fa fa-users-slash fa-2x mb-2 d-block"></i>
                    Sin grupos de timbrado.<br>
                    <small>Crea el primero con el botón <strong>Nuevo grupo</strong>.</small>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Estrategia</th>
                                <th class="text-center">Ring time</th>
                                <th>Fallback</th>
                                <th>Miembros</th>
                                <th class="text-center">Activo</th>
                                <th class="text-end" style="min-width:100px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in grupos" :key="g.id">
                                <td class="fw-medium">{{ g.nombre }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ g.estrategia }}</span>
                                </td>
                                <td class="text-center">{{ g.ring_time }}s</td>
                                <td class="text-muted">{{ g.destino_fallback }}</td>
                                <td>
                                    <span v-if="g.miembros.length === 0" class="text-muted small">Sin miembros</span>
                                    <span v-else>
                                        <span v-for="(m, i) in g.miembros" :key="m.id"
                                              class="badge bg-light text-dark border me-1">
                                            {{ m.numero }}
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <i :class="g.activo ? 'fa fa-toggle-on text-success' : 'fa fa-toggle-off text-secondary'" class="fa-lg"></i>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button v-if="canEdit"
                                                class="btn btn-outline-secondary"
                                                @click="abrirModal(g)"
                                                title="Editar">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <button v-if="canDelete"
                                                class="btn btn-outline-danger"
                                                @click="confirmarEliminar(g)"
                                                title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <div class="modal fade" id="modalGrupo" tabindex="-1" ref="modalEl">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa fa-users me-2"></i>
                            {{ editando ? 'Editar grupo' : 'Nuevo grupo de timbrado' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small">Nombre <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" v-model="form.nombre"
                                       placeholder="ej. Soporte técnico"
                                       :class="{'is-invalid': errores.nombre}">
                                <div class="invalid-feedback">{{ errores.nombre }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Ring time (seg)</label>
                                <input class="form-control form-control-sm" v-model.number="form.ring_time"
                                       type="number" min="5" max="120" placeholder="20">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small">Estrategia de timbrado</label>
                                <select class="form-select form-select-sm" v-model="form.estrategia">
                                    <option value="ringall">Ring all — todos a la vez</option>
                                    <option value="hunt">Hunt — en orden</option>
                                    <option value="memoryhunt">Memory hunt — orden rotativo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Destino si no contesta</label>
                                <select class="form-select form-select-sm" v-model="form.destino_fallback">
                                    <option value="colgar">Colgar</option>
                                    <option value="buzon">Buzón de voz</option>
                                    <option value="repetir">Repetir</option>
                                </select>
                            </div>

                            <!-- Miembros -->
                            <div class="col-12">
                                <label class="form-label small d-flex justify-content-between">
                                    <span>Extensiones miembro</span>
                                    <span class="text-muted">{{ form.miembros.length }} seleccionadas</span>
                                </label>
                                <div class="border rounded p-2" style="max-height:220px;overflow-y:auto">
                                    <div v-if="extensionesDisp.length === 0" class="text-muted small p-2">
                                        No hay extensiones disponibles.
                                    </div>
                                    <div v-for="(ext, idx) in extensionesDisp" :key="ext.id"
                                         class="d-flex align-items-center gap-2 py-1 border-bottom">
                                        <input type="checkbox"
                                               class="form-check-input mt-0"
                                               :id="`miembro_${ext.id}`"
                                               :checked="miembroSeleccionado(ext.id)"
                                               @change="toggleMiembro(ext, $event)">
                                        <label :for="`miembro_${ext.id}`" class="form-check-label small flex-grow-1">
                                            <code>{{ ext.numero }}</code> — {{ ext.nombre }}
                                        </label>
                                        <template v-if="miembroSeleccionado(ext.id)">
                                            <span class="text-muted small">Orden:</span>
                                            <input type="number" min="0" style="width:60px"
                                                   class="form-control form-control-sm"
                                                   :value="getMiembroOrden(ext.id)"
                                                   @change="setMiembroOrden(ext.id, $event.target.value)">
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" v-model="form.activo" id="chkActivoGrupo">
                                    <label class="form-check-label" for="chkActivoGrupo">Grupo activo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="guardar" :disabled="guardando">
                            <i class="fa fa-save me-1"></i>
                            {{ guardando ? 'Guardando…' : (editando ? 'Actualizar' : 'Crear grupo') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar -->
        <div class="modal fade" id="modalEliminarGrupo" tabindex="-1" ref="modalEliminarEl">
            <div class="modal-dialog">
                <div class="modal-content border-danger">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fa fa-trash me-2"></i>Eliminar grupo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" v-if="grupoAEliminar">
                        <p>¿Eliminar el grupo <strong>{{ grupoAEliminar.nombre }}</strong>?</p>
                        <p class="text-danger small">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger btn-sm"
                                @click="ejecutarEliminar" :disabled="eliminando">
                            {{ eliminando ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:11000">
            <div ref="toastEl" class="toast align-items-center border-0" role="alert"
                 :class="toastTipo === 'error' ? 'text-bg-danger' : 'text-bg-success'">
                <div class="d-flex">
                    <div class="toast-body">{{ toastMsg }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'VoipGruposTimbrado',

    props: {
        csrfToken: { type: String, required: true },
        baseUrl:   { type: String, required: true },
    },

    data() {
        return {
            grupos:           [],
            extensionesDisp:  [],
            cargando:         false,
            guardando:        false,
            eliminando:       false,
            editando:         null,
            grupoAEliminar:   null,
            toastMsg:         '',
            toastTipo:        'success',
            form:             this.formVacio(),
            errores:          {},
        };
    },

    computed: {
        canCreate() { return this.$store?.getters?.hasPermission?.('voip.grupos.create') ?? false; },
        canEdit()   { return this.$store?.getters?.hasPermission?.('voip.grupos.edit')   ?? false; },
        canDelete() { return this.$store?.getters?.hasPermission?.('voip.grupos.delete') ?? false; },
    },

    mounted() {
        this.cargar();
        this.cargarExtensiones();
    },

    methods: {
        formVacio() {
            return {
                nombre:           '',
                estrategia:       'ringall',
                ring_time:        20,
                destino_fallback: 'colgar',
                activo:           true,
                miembros:         [],
            };
        },

        async cargar() {
            this.cargando = true;
            try {
                const r = await fetch(`${this.baseUrl}/grupos-timbrado/data`, { headers: this.headers() });
                if (r.ok) this.grupos = await r.json();
            } finally {
                this.cargando = false;
            }
        },

        async cargarExtensiones() {
            const r = await fetch(`${this.baseUrl}/grupos-timbrado/extensiones-disponibles`, { headers: this.headers() });
            if (r.ok) this.extensionesDisp = await r.json();
        },

        miembroSeleccionado(extId) {
            return this.form.miembros.some(m => m.id === extId);
        },

        getMiembroOrden(extId) {
            return this.form.miembros.find(m => m.id === extId)?.orden ?? 0;
        },

        setMiembroOrden(extId, val) {
            const m = this.form.miembros.find(m => m.id === extId);
            if (m) m.orden = parseInt(val) || 0;
        },

        toggleMiembro(ext, event) {
            if (event.target.checked) {
                this.form.miembros.push({ id: ext.id, orden: this.form.miembros.length });
            } else {
                this.form.miembros = this.form.miembros.filter(m => m.id !== ext.id);
            }
        },

        abrirModal(grupo = null) {
            this.errores = {};
            if (grupo) {
                this.editando = grupo;
                this.form = {
                    nombre:           grupo.nombre,
                    estrategia:       grupo.estrategia,
                    ring_time:        grupo.ring_time,
                    destino_fallback: grupo.destino_fallback,
                    activo:           grupo.activo,
                    miembros:         grupo.miembros.map(m => ({ id: m.id, orden: m.orden })),
                };
            } else {
                this.editando = null;
                this.form = this.formVacio();
            }
            bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl).show();
        },

        async guardar() {
            this.errores  = {};
            this.guardando = true;
            const url    = this.editando
                ? `${this.baseUrl}/grupos-timbrado/${this.editando.id}`
                : `${this.baseUrl}/grupos-timbrado`;
            const method = this.editando ? 'PUT' : 'POST';

            try {
                const r    = await fetch(url, {
                    method,
                    headers: { ...this.headers(), 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const body = await r.json();

                if (r.status === 422) { this.errores = body.errors ?? {}; return; }
                if (!r.ok)           { this.toast('Error: ' + (body.error ?? r.statusText), 'error'); return; }

                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl).hide();
                this.toast(this.editando ? 'Grupo actualizado' : 'Grupo creado');
                await this.cargar();
            } finally {
                this.guardando = false;
            }
        },

        confirmarEliminar(g) {
            this.grupoAEliminar = g;
            bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).show();
        },

        async ejecutarEliminar() {
            if (!this.grupoAEliminar) return;
            this.eliminando = true;
            try {
                const r = await fetch(`${this.baseUrl}/grupos-timbrado/${this.grupoAEliminar.id}`, {
                    method: 'DELETE', headers: this.headers(),
                });
                bootstrap.Modal.getOrCreateInstance(this.$refs.modalEliminarEl).hide();
                if (r.ok) { this.toast('Grupo eliminado'); await this.cargar(); }
                else      { this.toast('Error al eliminar', 'error'); }
            } finally {
                this.eliminando = false;
            }
        },

        headers() {
            return { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' };
        },

        toast(msg, tipo = 'success') {
            this.toastMsg  = msg;
            this.toastTipo = tipo;
            bootstrap.Toast.getOrCreateInstance(this.$refs.toastEl, { delay: 3500 }).show();
        },
    },
};
</script>
