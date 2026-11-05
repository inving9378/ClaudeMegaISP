<template>
    <button type="button" :class="botonClass" @click="abrir">
        <span>Asignar trabajo</span>
    </button>

    <q-dialog v-model="abierto">
        <q-card class="at-card">
            <div class="at-head">Asignar trabajo · orden de trabajo de Talento</div>

            <div v-if="creada" class="at-body">
                <div class="alert alert-success mb-3">
                    Se creó la orden #{{ creada.id }}<span v-if="creada.tipo"> ({{ creada.tipo }})</span>
                    para {{ creada.colaborador || "la persona elegida" }}.
                    Le aparece en su app y en Talento › Órdenes de trabajo.
                </div>
                <div class="at-acciones">
                    <a class="btn btn-outline-secondary" href="/talento/ordenes">Ver órdenes</a>
                    <button type="button" class="btn btn-primary" @click="abierto = false">Listo</button>
                </div>
            </div>

            <form v-else class="at-body" @submit.prevent="guardar">
                <div class="mb-2">
                    <label class="form-label" for="at_tipo">Tipo de trabajo</label>
                    <select id="at_tipo" class="form-select" v-model="form.type_id" required>
                        <option value="">Elige el tipo…</option>
                        <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <SelectRolUsuario
                    :property="campoAsignar"
                    endpoint="/talento/api/ordenes/asignables"
                    v-model="form.colaborador_id"
                />

                <!-- Carga de trabajo (item #1158): cuántas órdenes hay cada día y de quién, por color de
                     tipo, para decidir si cabe una más ese día o va al siguiente. Tocar un día lo elige. -->
                <div class="mb-2">
                    <label class="form-label">Trabajo ya asignado</label>
                    <CargaCalendario v-model="diaElegido" :colaborador-id="form.colaborador_id" />
                </div>

                <!-- El día es obligatorio (2026-10-27): sin él la orden no le salía al técnico en «Mi día»;
                     la hora sigue siendo opcional. -->
                <div class="mb-2">
                    <label class="form-label" for="at_fecha">Fecha y hora</label>
                    <FechaHoraOrden v-model="form.programada" input-id="at_fecha" />
                    <div class="form-text">El día es obligatorio: ese día le aparece en «Mi día». La hora es opcional.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="at_notas">Notas (opcional)</label>
                    <textarea id="at_notas" class="form-control" rows="3" v-model="form.notes"></textarea>
                </div>

                <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

                <div class="at-acciones">
                    <button type="button" class="btn btn-outline-secondary" @click="abierto = false">Cancelar</button>
                    <button type="submit" class="btn btn-primary" :disabled="guardando || !listo">
                        {{ guardando ? "Creando…" : "Crear orden" }}
                    </button>
                </div>
            </form>
        </q-card>
    </q-dialog>
</template>

<script>
import axios from "axios";
import SelectRolUsuario from "../../../shared/SelectRolUsuario.vue";
import FechaHoraOrden from "./FechaHoraOrden.vue";
import CargaCalendario from "./CargaCalendario.vue";

/**
 * "Asignar trabajo" en la ficha del cliente o del prospecto (2026-10-22): crea una orden de
 * trabajo de Talento con el mismo POST /talento/api/ordenes que la pantalla de órdenes, eligiendo
 * primero el rol y después al colaborador activo.
 */
export default {
    name: "AsignarTrabajo",
    components: { SelectRolUsuario, FechaHoraOrden, CargaCalendario },
    props: {
        clientId: { type: [Number, String], default: null },
        crmId: { type: [Number, String], default: null },
        // G2: orden creada desde un ticket; el servidor toma el cliente del ticket si no se manda.
        ticketId: { type: [Number, String], default: null },
        botonClass: { type: String, default: "btn btn-outline-info" },
    },
    data() {
        return {
            abierto: false,
            tipos: [],
            crmLeadId: null,
            form: this.formVacio(),
            guardando: false,
            error: "",
            creada: null,
            campoAsignar: {
                field: "colaborador_id",
                label: "Asignar a",
                class_col: "full",
                class_label: "form-label",
                class_field: "",
            },
        };
    },
    computed: {
        // El día del calendario de carga y el de «Fecha y hora» son el mismo: elegir un día en el
        // calendario conserva la hora que ya se hubiera puesto.
        diaElegido: {
            get() {
                return this.form.programada ? this.form.programada.slice(0, 10) : null;
            },
            set(dia) {
                const hora = this.form.programada && this.form.programada.length > 10 ? this.form.programada.slice(10) : "";
                this.form.programada = dia ? dia + hora : null;
            },
        },
        listo() {
            return this.form.type_id && this.form.colaborador_id && this.form.programada && (this.clientId || this.crmLeadId || this.ticketId);
        },
    },
    methods: {
        formVacio() {
            // programada: el día solo o día y hora sin segundos (FechaHoraOrden).
            return { type_id: "", colaborador_id: null, programada: null, notes: "" };
        },
        async abrir() {
            this.form = this.formVacio();
            this.error = "";
            this.creada = null;
            this.abierto = true;
            try {
                const { data } = await axios.get("/talento/api/order-types");
                this.tipos = (data ?? []).filter((t) => t.active !== false && t.active !== 0);
            } catch (e) {
                this.error = "No se pudieron cargar los tipos de trabajo.";
            }
            if (this.crmId && !this.crmLeadId) {
                try {
                    const { data } = await axios.post(
                        `/crm/information/${this.crmId}/get-crm-main-information-id-and-crm-lead-information-id`
                    );
                    this.crmLeadId = data?.crmLeadInformationId ?? null;
                } catch (e) {
                    this.error = "No se pudo leer el prospecto.";
                }
            }
        },
        async guardar() {
            if (!this.form.programada) {
                this.error = "Elige el día de la orden.";
                return;
            }
            this.guardando = true;
            this.error = "";
            try {
                const { data } = await axios.post("/talento/api/ordenes", {
                    type_id: this.form.type_id,
                    colaborador_id: this.form.colaborador_id,
                    scheduled_at: this.form.programada,
                    notes: this.form.notes || null,
                    client_id: this.clientId || null,
                    crm_lead_id: this.clientId ? null : this.crmLeadId,
                    ticket_id: this.ticketId || null,
                });
                const usuario = data?.colaborador?.user;
                this.creada = {
                    id: data?.id ?? "",
                    tipo: data?.type?.name ?? null,
                    colaborador: [usuario?.name, usuario?.father_last_name].filter(Boolean).join(" ") || null,
                };
            } catch (e) {
                this.error =
                    e.response?.data?.error ??
                    e.response?.data?.message ??
                    "No se pudo crear la orden.";
            } finally {
                this.guardando = false;
            }
        },
    },
};
</script>

<style scoped>
.at-card {
    width: 600px;
    max-width: 94vw;
}
.at-head {
    padding: 14px 18px;
    font-weight: 600;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
.at-body {
    padding: 16px 18px;
}
.at-acciones {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
