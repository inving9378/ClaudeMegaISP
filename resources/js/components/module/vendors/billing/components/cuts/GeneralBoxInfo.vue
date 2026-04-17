<template>
    <div class="row q-py-md no-gutter-x">
        <div class="col">
            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
                    <div class="column">
                        Caja: {{ box?.id ?? "-" }}<br />
                        Operador: {{ box?.user_str ?? "-" }}
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
                    <div class="column">
                        Fecha: {{ box?.start_date ?? "-" }}
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
                    <div class="column">
                        Inicio: {{ box?.start_time ?? "-" }}<br />
                        Finalizado: {{ box?.end_time ?? "-" }}
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3 col-lg-3 col-xl-3">
                    <div class="column">
                        Sucursal: {{ box?.sucursal ?? "-" }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col col-auto" v-if="different">
            <q-btn
                color="teal"
                icon="mdi-reply"
                @click="emits('change-actual-box')"
            >
                <q-tooltip>Cambiar a caja actual</q-tooltip>
            </q-btn>
        </div>
        <div
            class="col col-auto"
            style="padding-left: 5px !important"
            v-if="box.closed"
        >
            <q-btn
                color="teal"
                icon="mdi-file-pdf"
                target="_blank"
                :href="`/sellers/cuts/box-pdf/${box.id}`"
            >
                <q-tooltip>Exportar a PDF</q-tooltip>
            </q-btn>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    box: Object,
    different: Boolean,
});

const emits = defineEmits(["change-actual-box"]);
</script>
