<template>
    <div class="crme-srv">
        <h4 class="mb-3">Servicios del expediente</h4>
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-if="ctx.iva_pct === null" class="alert alert-warning">No hay IVA configurado en la empresa; no se pueden calcular totales.</div>

        <div v-if="loading" class="text-center text-muted py-4"><div class="spinner-border"></div></div>
        <template v-else>
            <div v-if="!items.length" class="text-muted mb-3">Este expediente aún no tiene servicios configurados.</div>
            <table v-else class="table table-sm align-middle">
                <thead><tr><th>Servicio</th><th>Sitio</th><th class="text-end">Lista</th><th class="text-end">Aprobado</th><th class="text-end">IVA</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="s in items" :key="s.id">
                        <td>{{ nombreServicio(s) }}</td>
                        <td>{{ nombreSitio(s.sitio_id) }}</td>
                        <td class="text-end">{{ money(s.precio_lista) }}</td>
                        <td class="text-end">{{ money(s.precio_aprobado) }}
                            <span v-if="s.aprobado_por" class="badge bg-warning text-dark ms-1" title="Precio distinto al de lista aprobado">aprobado</span></td>
                        <td class="text-end">{{ money(s.iva_importe) }}</td>
                        <td class="text-end fw-semibold">{{ money(s.total) }}</td>
                        <td class="text-end text-nowrap">
                            <template v-if="ctx.puede_editar">
                                <button class="btn btn-sm btn-outline-primary me-1" @click="abrir(s)">Editar</button>
                                <button class="btn btn-sm btn-outline-danger" @click="borrar(s)">Quitar</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button v-if="ctx.puede_editar && !form" class="btn btn-primary" @click="abrir(null)">Agregar servicio</button>
        </template>

        <div v-if="form" class="card mt-3"><div class="card-body">
            <h6>{{ form.id ? 'Editar servicio' : 'Nuevo servicio' }}</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Servicio</label>
                    <select class="form-select" v-model="sel" @change="alElegir">
                        <option value="">— elegir —</option>
                        <option v-for="c in ctx.catalogo" :key="c.tipo + c.id" :value="c.tipo + ':' + c.id">{{ c.nombre }} ({{ c.tipo }})</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sitio de instalación</label>
                    <select class="form-select" v-model="form.sitio_id">
                        <option :value="null">— sin sitio —</option>
                        <option v-for="x in ctx.sitios" :key="x.id" :value="x.id">{{ x.nombre }}</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Precio de lista</label><input class="form-control" :value="money(lista)" disabled></div>
                <div class="col-md-3"><label class="form-label">Precio aprobado</label><input type="number" min="0" step="0.01" class="form-control" v-model="form.precio_aprobado" placeholder="Igual a lista"></div>
                <div class="col-md-3"><label class="form-label">Plazo (meses)</label><input type="number" min="0" class="form-control" v-model="form.plazo_meses"></div>
                <div class="col-md-3"><label class="form-label">IPv4 solicitadas</label><input type="number" min="0" class="form-control" v-model="form.ipv4_solicitadas"></div>
                <div class="col-md-3"><label class="form-label">Bajada</label><input class="form-control" v-model="form.velocidad_bajada"></div>
                <div class="col-md-3"><label class="form-label">Subida</label><input class="form-control" v-model="form.velocidad_subida"></div>
                <div class="col-md-3"><label class="form-label">Entrega estimada</label><input type="date" class="form-control" v-model="form.fecha_estimada_entrega"></div>
                <div class="col-md-3"><label class="form-label">Vigencia propuesta</label><input type="date" class="form-control" v-model="form.vigencia_propuesta"></div>
            </div>

            <div v-if="requiereAprobacion" class="alert alert-warning mt-3 mb-0">
                El precio difiere del de lista: requiere justificación y el permiso de aprobar precios.
                <span v-if="!ctx.puede_aprobar" class="fw-semibold"> Tu usuario no lo tiene; el guardado será rechazado.</span>
                <textarea class="form-control mt-2" rows="2" v-model="form.justificacion_precio" placeholder="Justificación del precio"></textarea>
            </div>

            <div class="mt-3 p-2 bg-light border rounded">
                Base {{ money(base) }} + IVA ({{ ctx.iva_pct ?? '—' }}%) {{ money(ivaImp) }} = <strong>{{ money(total) }}</strong>
            </div>

            <div class="mt-3"><h6>Redundancia (garantías verificadas)</h6>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label">Rutas físicas</label><input type="number" min="0" class="form-control" v-model="form.red_rutas_fisicas"></div>
                    <div class="col-md-4"><label class="form-label">Conmutación</label>
                        <select class="form-select" v-model="form.red_conmutacion"><option :value="null">—</option><option value="automatica">Automática</option><option value="manual">Manual</option></select></div>
                    <div class="col-md-4"><label class="form-label">Disponibilidad contractual</label><input class="form-control" v-model="form.red_disponibilidad_contractual"></div>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-2">
                    <label><input type="checkbox" v-model="form.red_proteccion_logica"> Protección lógica</label>
                    <label><input type="checkbox" v-model="form.red_diversidad_equipos"> Diversidad de equipos</label>
                    <label><input type="checkbox" v-model="form.red_diversidad_proveedores"> Diversidad de proveedores</label>
                    <label><input type="checkbox" v-model="form.red_verificada"> Verificada por ingeniería</label>
                </div>
                <small class="text-muted">Una doble ruta física no se presenta como redundancia total; sólo cuenta lo verificado.</small>
            </div>

            <div class="mt-3">
                <button class="btn btn-primary me-2" :disabled="guardando" @click="guardar">Guardar</button>
                <button class="btn btn-outline-secondary" @click="form = null">Cancelar</button>
            </div>
        </div></div>
    </div>
</template>

<script>
import axios from 'axios';

const CAMPOS = ['sitio_id', 'servicio_tipo', 'servicio_id', 'velocidad_bajada', 'velocidad_subida', 'plazo_meses',
    'precio_aprobado', 'justificacion_precio', 'ipv4_solicitadas', 'fecha_estimada_entrega', 'vigencia_propuesta',
    'red_rutas_fisicas', 'red_proteccion_logica', 'red_diversidad_equipos', 'red_diversidad_proveedores',
    'red_disponibilidad_contractual', 'red_conmutacion', 'red_verificada'];

export default {
    name: 'CrmeServicios',
    props: { expedienteId: { type: Number, required: true } },
    data() {
        return { items: [], ctx: { iva_pct: null, catalogo: [], sitios: [], puede_aprobar: false, puede_editar: false },
            loading: true, error: '', form: null, sel: '', guardando: false };
    },
    computed: {
        api() { return `/crm-empresarial/api/expedientes/${this.expedienteId}`; },
        lista() {
            const c = this.ctx.catalogo.find(x => x.tipo === this.form?.servicio_tipo && x.id === this.form?.servicio_id);
            return c ? c.precio_lista : null;
        },
        aprobadoNum() { const v = this.form?.precio_aprobado; return v === '' || v === null || v === undefined ? null : Number(v); },
        base() { return this.aprobadoNum ?? this.lista; },
        ivaImp() { return this.base === null || this.ctx.iva_pct === null ? null : Math.round(this.base * this.ctx.iva_pct) / 100; },
        total() { return this.ivaImp === null ? null : Math.round((this.base + this.ivaImp) * 100) / 100; },
        requiereAprobacion() {
            return this.aprobadoNum !== null && this.lista !== null && Math.round(this.aprobadoNum * 100) !== Math.round(this.lista * 100);
        },
    },
    mounted() { this.cargar(); },
    methods: {
        money(v) { return v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }); },
        nombreServicio(s) { return this.ctx.catalogo.find(c => c.tipo === s.servicio_tipo && c.id === s.servicio_id)?.nombre || '—'; },
        nombreSitio(id) { return this.ctx.sitios.find(x => x.id === id)?.nombre || '—'; },
        async cargar() {
            this.loading = true; this.error = '';
            try {
                const [a, b] = await Promise.all([axios.get(`${this.api}/servicios`), axios.get(`${this.api}/servicios-contexto`)]);
                this.items = a.data; this.ctx = b.data;
            } catch (e) { this.error = this.msg(e); }
            this.loading = false;
        },
        msg(e) { const d = e.response?.data; return d?.message || (d?.errors && Object.values(d.errors).flat().join(' ')) || 'No se pudo completar la operación.'; },
        abrir(s) {
            this.form = {}; CAMPOS.forEach(k => { this.form[k] = s ? s[k] ?? null : null; });
            this.form.id = s?.id || null;
            this.sel = s && s.servicio_tipo ? `${s.servicio_tipo}:${s.servicio_id}` : '';
        },
        alElegir() {
            const [t, i] = this.sel ? this.sel.split(':') : [null, null];
            this.form.servicio_tipo = t; this.form.servicio_id = i ? Number(i) : null;
        },
        async guardar() {
            this.guardando = true; this.error = '';
            const body = {}; CAMPOS.forEach(k => { const v = this.form[k]; body[k] = v === '' ? null : v; });
            try {
                if (this.form.id) await axios.put(`${this.api}/servicios/${this.form.id}`, body);
                else await axios.post(`${this.api}/servicios`, body);
                this.form = null; await this.cargar();
            } catch (e) { this.error = this.msg(e); }
            this.guardando = false;
        },
        async borrar(s) {
            if (!confirm('¿Quitar este servicio del expediente?')) return;
            try { await axios.delete(`${this.api}/servicios/${s.id}`); await this.cargar(); } catch (e) { this.error = this.msg(e); }
        },
    },
};
</script>
