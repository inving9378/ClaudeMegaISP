<template>
    <div>
        <p class="text-muted">
            Matriz de permisos por rol: cuáles permisos asignados (<code>role_has_permissions</code>)
            realmente surten efecto (se consumen en el código) contra cuáles son huérfanos.
            Solo lectura — no modifica roles, permisos ni asignaciones.
        </p>

        <q-banner v-if="!loading && !fuenteCsv" class="bg-warning text-dark q-mb-md" dense>
            No se encontró <code>docs/auditoria/permisos-punto1-2.csv</code> (Fase 4a) — el estado de
            cada permiso se recalculó en vivo (grep puntual), puede tardar más.
        </q-banner>

        <div class="row q-col-gutter-md q-mb-md">
            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section>
                        <div class="text-caption text-grey">Roles inflados</div>
                        <div class="text-h5" :class="rolesInflados.length ? 'text-negative' : 'text-positive'">
                            {{ rolesInflados.length }}
                        </div>
                        <div class="text-caption text-grey">más permisos huérfanos que efectivos</div>
                    </q-card-section>
                </q-card>
            </div>
            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section>
                        <div class="text-caption text-grey">Alto riesgo sin efecto</div>
                        <div class="text-h5" :class="altoRiesgoSinEfecto.length ? 'text-negative' : 'text-positive'">
                            {{ altoRiesgoSinEfecto.length }}
                        </div>
                        <div class="text-caption text-grey">permisos de dinero/auth/prod asignados donde no protegen nada</div>
                    </q-card-section>
                </q-card>
            </div>
            <div class="col-12 col-md-4">
                <q-card flat bordered>
                    <q-card-section>
                        <div class="text-caption text-grey">Roles evaluados</div>
                        <div class="text-h5">{{ resumenRoles.length }}</div>
                    </q-card-section>
                </q-card>
            </div>
        </div>

        <q-table
            :rows="resumenRoles"
            :columns="columns"
            row-key="rol"
            flat
            bordered
            :loading="loading"
            :pagination="{ rowsPerPage: 0 }"
            @row-click="(evt, row) => openDetalle(row)"
        >
            <template #body-cell-inflado="props">
                <q-td :props="props">
                    <q-badge v-if="props.row.inflado" color="negative" label="Inflado" />
                </q-td>
            </template>
            <template #body-cell-huerfanos="props">
                <q-td :props="props">
                    <span :class="props.row.huerfanos > 0 ? 'text-negative text-weight-medium' : ''">
                        {{ props.value }}
                    </span>
                </q-td>
            </template>
        </q-table>

        <div v-if="altoRiesgoSinEfecto.length" class="q-mt-lg">
            <div class="text-subtitle1 text-negative q-mb-sm">
                Permisos de ALTO RIESGO asignados donde NO surten efecto (falsa protección)
            </div>
            <q-list bordered separator>
                <q-item v-for="(x, i) in altoRiesgoSinEfecto" :key="i">
                    <q-item-section>
                        <q-item-label>
                            rol <b>{{ x.rol }}</b> → <code>{{ x.permiso }}</code>
                        </q-item-label>
                    </q-item-section>
                </q-item>
            </q-list>
        </div>

        <!-- Detalle de un rol: sus permisos, cuáles surten efecto -->
        <q-dialog v-model="showDetalle">
            <q-card style="min-width: 520px; max-width: 90vw;">
                <q-card-section class="row items-center">
                    <div class="text-h6">Permisos del rol: {{ rolSeleccionado }}</div>
                    <q-space />
                    <q-btn icon="close" flat round dense v-close-popup />
                </q-card-section>
                <q-card-section style="max-height: 60vh; overflow-y: auto;">
                    <q-table
                        :rows="filasDelRol"
                        :columns="columnsDetalle"
                        row-key="permiso"
                        flat
                        dense
                        :pagination="{ rowsPerPage: 0 }"
                    >
                        <template #body-cell-surte_efecto="props">
                            <q-td :props="props">
                                <q-badge
                                    :color="props.row.surte_efecto === true ? 'positive' : (props.row.surte_efecto === false ? 'negative' : 'grey')"
                                    :label="props.row.surte_efecto === true ? 'Efectivo' : (props.row.surte_efecto === false ? 'Huérfano' : 'Desconocido')"
                                />
                            </q-td>
                        </template>
                        <template #body-cell-alto_riesgo="props">
                            <q-td :props="props">
                                <q-badge v-if="props.row.alto_riesgo" color="warning" text-color="dark" label="Alto riesgo" />
                            </q-td>
                        </template>
                    </q-table>
                </q-card-section>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
export default {
    name: 'AuditoriaPermisosMatriz',
    data() {
        return {
            loading: false,
            fuenteCsv: true,
            filas: [],
            resumenRoles: [],
            rolesInflados: [],
            altoRiesgoSinEfecto: [],
            showDetalle: false,
            rolSeleccionado: null,
            columns: [
                { name: 'rol', label: 'Rol', field: 'rol', align: 'left', sortable: true },
                { name: 'usuarios', label: 'Usuarios', field: 'usuarios', align: 'center', sortable: true },
                { name: 'total', label: 'Total permisos', field: 'total', align: 'center', sortable: true },
                { name: 'efectivos', label: 'Efectivos', field: 'efectivos', align: 'center', sortable: true },
                { name: 'huerfanos', label: 'Huérfanos', field: 'huerfanos', align: 'center', sortable: true },
                { name: 'desconocidos', label: 'Desconocidos', field: 'desconocidos', align: 'center' },
                { name: 'inflado', label: '', field: 'inflado', align: 'center' },
            ],
            columnsDetalle: [
                { name: 'permiso', label: 'Permiso', field: 'permiso', align: 'left', sortable: true },
                { name: 'guard', label: 'Guard', field: 'guard', align: 'left' },
                { name: 'surte_efecto', label: 'Estado', field: 'surte_efecto', align: 'center' },
                { name: 'alto_riesgo', label: '', field: 'alto_riesgo', align: 'center' },
            ],
        };
    },
    computed: {
        filasDelRol() {
            if (!this.rolSeleccionado) return [];
            return this.filas.filter((f) => f.rol === this.rolSeleccionado);
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/administracion/auditoria-permisos-matriz/data');
                this.filas = data.filas || [];
                this.resumenRoles = data.resumen_roles || [];
                this.rolesInflados = data.roles_inflados || [];
                this.altoRiesgoSinEfecto = data.alto_riesgo_sin_efecto || [];
                this.fuenteCsv = !!data.fuente_csv;
            } catch (e) {
                this.$q.notify({ type: 'negative', message: 'No se pudo cargar la matriz de permisos.' });
            } finally {
                this.loading = false;
            }
        },
        openDetalle(row) {
            this.rolSeleccionado = row.rol;
            this.showDetalle = true;
        },
    },
};
</script>
