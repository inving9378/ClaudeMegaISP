<template>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fa fa-cubes me-2"></i>{{ isEdit ? "Editar" : "Nuevo" }} servicio contratable
            </h5>
            <a href="/planes/contratables" class="btn btn-light btn-sm"><i class="fa fa-arrow-left me-1"></i>Volver</a>
        </div>
        <div class="card-body">
            <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

            <form v-else @submit.prevent="submit">
                <!-- Datos del servicio -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Módulo</label>
                        <select class="form-select" v-model="form.module_key" :disabled="isEdit" @change="onModuleChange">
                            <option value="" disabled>Elige un módulo…</option>
                            <option v-for="m in modulos" :key="m.key" :value="m.key">{{ m.label }}</option>
                        </select>
                        <small class="text-muted">Métrica: <strong>{{ metricaActual || "—" }}</strong> (derivada del módulo)</small>
                        <div v-if="errors.module_key" class="text-danger small">{{ errors.module_key }}</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" v-model="form.nombre" maxlength="255" />
                        <div v-if="errors.nombre" class="text-danger small">{{ errors.nombre }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Facturas de prueba gratis</label>
                        <input type="number" class="form-control" v-model.number="form.meses_prueba" min="0" max="60" />
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" rows="2" v-model="form.descripcion"></textarea>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">IVA %</label>
                        <input type="number" class="form-control" v-model.number="form.iva_porcentaje" min="0" max="100" step="0.01" />
                    </div>
                    <div class="col-md-2 d-flex flex-column justify-content-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="aplicaIva" v-model="form.aplica_iva" />
                            <label class="form-check-label" for="aplicaIva">Aplica IVA</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="activo" v-model="form.activo" />
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4" />

                <!-- Paquetes por rango -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Paquetes por rango de unidades</h6>
                    <button type="button" class="btn btn-outline-primary btn-sm" @click="addPackage">
                        <i class="fa fa-plus me-1"></i>Agregar paquete
                    </button>
                </div>
                <div class="alert alert-info py-2 small">
                    <i class="fa fa-info-circle me-1"></i>
                    El <strong>precio es mensual y con IVA INCLUIDO</strong> (igual que el resto del sistema).
                    Deja el <strong>rango máximo vacío</strong> en el último paquete para “sin tope”.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width: 28%">Nombre del paquete</th>
                                <th style="width: 15%">Rango mín.</th>
                                <th style="width: 15%">Rango máx. <small class="text-muted">(vacío = sin tope)</small></th>
                                <th style="width: 22%">Precio mensual (IVA incl.)</th>
                                <th style="width: 10%">Orden</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in packages" :key="i">
                                <td><input type="text" class="form-control form-control-sm" v-model="p.nombre" /></td>
                                <td><input type="number" class="form-control form-control-sm" v-model.number="p.rango_min" min="1" /></td>
                                <td><input type="number" class="form-control form-control-sm" v-model.number="p.rango_max" min="1" placeholder="sin tope" /></td>
                                <td><input type="number" class="form-control form-control-sm" v-model.number="p.precio" min="0" step="0.01" /></td>
                                <td><input type="number" class="form-control form-control-sm" v-model.number="p.orden" min="0" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" @click="removePackage(i)">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!packages.length">
                                <td colspan="6" class="text-center text-muted">Agrega al menos un paquete.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="rangeError" class="alert alert-danger py-2 small mb-3">
                    <i class="fa fa-exclamation-triangle me-1"></i>{{ rangeError }}
                </div>
                <div v-if="errors.packages" class="alert alert-danger py-2 small mb-3">{{ errors.packages }}</div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="/planes/contratables" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <i class="fa fa-save me-1"></i>{{ saving ? "Guardando…" : "Guardar" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
export default {
    name: "ContratableCatalogForm",
    props: {
        id: { type: [String, Number], default: "" },
    },
    data() {
        return {
            loading: true,
            saving: false,
            modulos: [],
            form: {
                module_key: "",
                nombre: "",
                descripcion: "",
                activo: true,
                meses_prueba: 3,
                aplica_iva: true,
                iva_porcentaje: 16.0,
            },
            packages: [],
            errors: {},
        };
    },
    computed: {
        isEdit() {
            return !!this.id && this.id !== "" && this.id !== "null";
        },
        metricaActual() {
            const m = this.modulos.find((x) => x.key === this.form.module_key);
            return m ? m.metrica : "";
        },
        // Validación de rangos lado cliente (espejo del servidor).
        rangeError() {
            const ps = this.packages;
            if (!ps.length) return "";
            for (const p of ps) {
                if (p.rango_max != null && p.rango_max !== "" && Number(p.rango_max) < Number(p.rango_min)) {
                    return "Hay un paquete con rango máximo menor que el mínimo.";
                }
            }
            const abiertos = ps.filter((p) => p.rango_max == null || p.rango_max === "");
            if (abiertos.length > 1) return "Solo un paquete puede quedar sin tope superior.";

            const sorted = [...ps].sort((a, b) => Number(a.rango_min) - Number(b.rango_min));
            let prevMax = -Infinity;
            let prevOpen = false;
            for (let i = 0; i < sorted.length; i++) {
                const min = Number(sorted[i].rango_min);
                const open = sorted[i].rango_max == null || sorted[i].rango_max === "";
                if (prevOpen) return "El paquete sin tope debe ser el de mayor rango (el último).";
                if (i > 0 && min <= prevMax) return `Los rangos se solapan cerca de ${min}.`;
                prevMax = open ? Infinity : Number(sorted[i].rango_max);
                prevOpen = open;
            }
            return "";
        },
    },
    async mounted() {
        try {
            const { data: mods } = await axios.get("/planes/contratables/modulos");
            this.modulos = mods.modulos || [];
            if (this.isEdit) {
                const { data } = await axios.get(`/planes/contratables/show/${this.id}`);
                const s = data.service;
                this.form = {
                    module_key: s.module_key,
                    nombre: s.nombre,
                    descripcion: s.descripcion,
                    activo: !!s.activo,
                    meses_prueba: Number(s.meses_prueba),
                    aplica_iva: !!s.aplica_iva,
                    iva_porcentaje: Number(s.iva_porcentaje),
                };
                this.packages = (s.packages || []).map((p) => ({
                    nombre: p.nombre,
                    rango_min: p.rango_min,
                    rango_max: p.rango_max,
                    precio: Number(p.precio),
                    orden: p.orden,
                }));
            } else {
                this.addPackage();
            }
        } finally {
            this.loading = false;
        }
    },
    methods: {
        onModuleChange() {
            this.errors.module_key = "";
        },
        addPackage() {
            this.packages.push({ nombre: "", rango_min: 1, rango_max: null, precio: 0, orden: this.packages.length + 1 });
        },
        removePackage(i) {
            this.packages.splice(i, 1);
        },
        async submit() {
            this.errors = {};
            if (this.rangeError) return;
            if (!this.form.module_key) {
                this.errors.module_key = "Elige un módulo.";
                return;
            }
            this.saving = true;
            const payload = {
                ...this.form,
                packages: this.packages.map((p) => ({
                    nombre: p.nombre,
                    rango_min: p.rango_min,
                    rango_max: p.rango_max === "" ? null : p.rango_max,
                    precio: p.precio,
                    orden: p.orden,
                })),
            };
            const url = this.isEdit ? `/planes/contratables/update/${this.id}` : "/planes/contratables/add";
            try {
                await axios.post(url, payload);
                window.location.href = "/planes/contratables";
            } catch (e) {
                const r = e.response;
                if (r && r.status === 422) {
                    const errs = r.data.errors || {};
                    // Aplanar mensajes (Laravel devuelve arrays por campo).
                    const flat = {};
                    Object.keys(errs).forEach((k) => (flat[k] = Array.isArray(errs[k]) ? errs[k][0] : errs[k]));
                    this.errors = flat;
                } else {
                    alert("No se pudo guardar: " + (r?.data?.message || e.message));
                }
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
