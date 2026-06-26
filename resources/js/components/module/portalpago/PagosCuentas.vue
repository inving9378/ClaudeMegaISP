<template>
    <div class="q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h6">Cuentas de Cobro</div>
            <q-space />
            <q-btn color="primary" label="Nueva cuenta" @click="abrirNueva" />
        </div>

        <q-table :rows="rows" :columns="columns" row-key="id" flat bordered :loading="loading"
                 :pagination="{ rowsPerPage: 15 }" no-data-label="Sin cuentas de cobro registradas">
            <template #body-cell-activa="props">
                <q-td :props="props">
                    <q-badge :color="props.value ? 'positive' : 'grey'" :label="props.value ? 'Activa' : 'Inactiva'" />
                </q-td>
            </template>
            <template #body-cell-acciones="props">
                <q-td :props="props">
                    <q-btn flat dense color="primary" label="Editar" @click="abrirEditar(props.row)" />
                    <q-btn flat dense :color="props.row.activa ? 'negative' : 'positive'"
                           :label="props.row.activa ? 'Desactivar' : 'Activar'" @click="toggle(props.row)" />
                </q-td>
            </template>
        </q-table>

        <q-dialog v-model="form.show">
            <q-card style="min-width: 420px; max-width: 90vw">
                <q-card-section class="bg-primary text-white text-h6">
                    {{ form.id ? 'Editar cuenta' : 'Nueva cuenta de cobro' }}
                </q-card-section>
                <q-card-section class="q-gutter-sm">
                    <q-input v-model="form.nombre" label="Nombre *" outlined dense :error="!!errs.nombre" :error-message="errs.nombre" />
                    <q-input v-model="form.clabe" label="CLABE (18 dígitos) *" outlined dense mask="##################"
                             :error="!!errs.clabe" :error-message="errs.clabe" hint="Exactamente 18 dígitos" />
                    <q-input v-model="form.banco" label="Banco" outlined dense />
                    <q-input v-model="form.titular" label="Titular" outlined dense />
                    <q-input v-model="form.beneficiario" label="Beneficiario" outlined dense />
                    <q-toggle v-model="form.activa" label="Activa" />
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat label="Cancelar" v-close-popup />
                    <q-btn color="primary" label="Guardar" :loading="form.busy" @click="guardar" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>
</template>

<script>
export default {
    name: 'PagosCuentas',
    data() {
        return {
            rows: [],
            loading: false,
            errs: {},
            form: { show: false, busy: false, id: null, nombre: '', clabe: '', banco: '', titular: '', beneficiario: '', activa: true },
            columns: [
                { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' },
                { name: 'clabe', label: 'CLABE', field: 'clabe', align: 'left' },
                { name: 'banco', label: 'Banco', field: 'banco', align: 'left' },
                { name: 'beneficiario', label: 'Beneficiario', field: 'beneficiario', align: 'left' },
                { name: 'activa', label: 'Estado', field: 'activa', align: 'center' },
                { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'right' },
            ],
        };
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try { const { data } = await axios.get('/api/pagos/cuentas'); this.rows = data; }
            catch (e) { console.error(e); }
            finally { this.loading = false; }
        },
        abrirNueva() {
            this.errs = {};
            this.form = { show: true, busy: false, id: null, nombre: '', clabe: '', banco: '', titular: '', beneficiario: '', activa: true };
        },
        abrirEditar(row) {
            this.errs = {};
            this.form = { show: true, busy: false, id: row.id, nombre: row.nombre, clabe: row.clabe, banco: row.banco || '', titular: row.titular || '', beneficiario: row.beneficiario || '', activa: !!row.activa };
        },
        async guardar() {
            this.errs = {};
            this.form.busy = true;
            const payload = { nombre: this.form.nombre, clabe: this.form.clabe, banco: this.form.banco, titular: this.form.titular, beneficiario: this.form.beneficiario, activa: this.form.activa };
            try {
                if (this.form.id) await axios.put(`/api/pagos/cuentas/${this.form.id}`, payload);
                else await axios.post('/api/pagos/cuentas', payload);
                this.notify('Cuenta guardada', 'positive');
                this.form.show = false;
                this.load();
            } catch (e) {
                if (e.response?.status === 422) {
                    const errs = e.response.data.errors || {};
                    this.errs = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
                } else {
                    this.notify('No se pudo guardar', 'negative');
                }
            } finally { this.form.busy = false; }
        },
        async toggle(row) {
            try { const { data } = await axios.post(`/api/pagos/cuentas/${row.id}/toggle`); this.notify(data.message, 'positive'); this.load(); }
            catch (e) { this.notify('No se pudo cambiar el estado', 'negative'); }
        },
        notify(message, color) {
            if (this.$q && this.$q.notify) this.$q.notify({ message, color });
            else alert(message);
        },
    },
};
</script>
