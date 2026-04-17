<template>
    <q-menu>
        <q-list dense>
            <template
                v-for="key in Object.keys(row[commission])"
                :key="`${row.period}-${key}`"
            >
                <template v-if="key === 'LVA'">
                    <q-item v-if="row[commission][key].length > 0">
                        <q-list dense>
                            <q-item
                                v-for="client in row[commission][key]"
                                :key="`client-${client.label}`"
                            >
                                <q-item-section>
                                    {{ client.label }}
                                </q-item-section>
                                <q-item-section avatar>
                                    <span
                                        class="text-primary"
                                        v-if="client.state === 'pagada'"
                                        >PAGADA</span
                                    >
                                    <span
                                        class="text-danger"
                                        v-else-if="
                                            client.state === 'descontada'
                                        "
                                        >DESCONTADA</span
                                    >
                                    <span class="text-success" v-else
                                        >PENDIENTE</span
                                    >
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </q-item>
                </template>
                <template v-else-if="key === 'LVC'">
                    <q-item v-if="row[commission][key].length > 0">
                        <q-list dense>
                            <q-item
                                v-for="client in row[commission][key]"
                                :key="`client-${client}`"
                            >
                                <q-item-section>
                                    {{ client }}
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </q-item>
                </template>
                <template v-else-if="key !== 'sales_amount'">
                    <q-item>
                        <q-item-section class="text-bold">
                            {{ key }}
                        </q-item-section>
                        <q-item-section avatar>
                            {{ getRoundValue(row, commission, key) }}
                        </q-item-section>
                    </q-item>
                </template>
            </template>
        </q-list>
    </q-menu>
</template>

<script setup>
import { defineComponent } from "vue";

defineComponent({
    name: "MenuGeneralInformationTable",
});

const props = defineProps({
    row: Object,
    commission: String,
});

const getRoundValue = (result, name, key) => {
    if (!isNaN(result[name][key])) {
        return getRound(result[name][key]);
    }
    return result[name][key];
};

const getRound = (val) => {
    if (!isNaN(val)) {
        return Math.round(val * 100) / 100;
    }
    return parseFloat("0");
};
</script>

<style>
.row.no-gutter-x,
.q-toolbar.row,
.q-item.row,
.q-tabs.row,
.object-field .q-checkbox.row.disabled {
    --bs-gutter-x: 0px !important;
}
.object-field .row {
    --bs-gutter-x: 0 !important;
    --bs-gutter-y: 0;
    -ms-flex-wrap: wrap !important;
    flex-wrap: inherit !important;
}
.q-field__after.row,
.q-field__prefix.row,
.q-btn__content.row,
.q-btn,
.q-item__section,
.q-checkbox__inner,
.q-checkbox__label,
.q-icon,
.q-tabs__content.row {
    width: auto !important;
}

.row.no-wrap {
    flex-wrap: nowrap !important;
}

.q-chip {
    padding: 0.5em 0.9em !important;
    margin: 4px !important;
}

.q-field__append {
    width: auto;
}

.q-field__bottom.row {
    padding-left: 0px !important;
}

.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip {
    width: auto !important;
    position: relative !important;
    right: 0px !important;
}

.q-icon.notranslate.material-icons.q-select__dropdown-icon.rotate-180,
.q-field__append.q-field__marginal.row.no-wrap.items-center.q-anchor--skip
    button {
    right: 0px !important;
}

.q-field__append span {
    width: 40px;
    background-color: #e9e9ef;
    text-align: center;
}

.q-field__native.d-flex {
    display: -webkit-box !important;
}
.q-field--auto-height .q-field__native,
.q-field--auto-height .q-field__prefix,
.q-field--auto-height .q-field__suffix {
    line-height: 26px !important;
}

.q-field--outlined .q-field__control {
    padding: 0 2px !important;
}
.q-field__control-container.row {
    margin-right: 10px !important;
}
#toast-container {
    z-index: 9999999 !important;
}
.q-field__control-container.row,
.q-field__control-container.row .q-field__native {
    padding-right: 0px !important;
}
</style>
