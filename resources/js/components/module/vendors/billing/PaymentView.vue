<template>
    <modal
        :show="props.showModal"
        :size="'lg'"
        title="Vista previa del pago"
        @update:show="updateShow"
    >
        <template #body>
            <div class="row text-white q-pa-md" style="background: #3668d3">
                <div class="col self-center">
                    <img
                        src="/images/logo_meganet_oficial.png"
                        alt=""
                        height="70"
                        style="display: block"
                    />
                </div>
                <div class="col">
                    <strong>MEGANET TELECOMUNICACIONES S.A. DE C.V.</strong
                    ><br />
                    Código Postal: {{ data?.company?.company_postal_code
                    }}<br />
                    RFC: {{ data?.company?.rfc }}<br />
                    Atención a clientes:
                    {{ data?.company?.atention_client_phone }}<br />
                </div>
            </div>
            <div class="q-my-sm"><b> Fecha: </b> {{ data?.payment_date }}</div>
            <div class="q-my-sm">
                <b> Número de recibo: </b> {{ data?.invoice_number }}
            </div>
            <div class="q-my-sm"><b> Nombre y apellidos: </b> {{ user }}</div>
            <div class="q-my-sm">
                <b> Método de pago: </b> {{ data?.payment_method }}
            </div>
            <div class="q-my-sm"><b> Total pagado: </b> ${{ data?.total }}</div>
            <div class="q-my-sm" v-if="week_commissions">
                <b> Comisiones pagadas </b>
                <q-list dense>
                    <q-item
                        v-for="week in Object.keys(week_commissions)"
                        :key="`week-${week}`"
                    >
                        <q-list dense>
                            <q-item>
                                <q-item-section class="text-bold">
                                    {{ getDateFormat(week) }}
                                </q-item-section>
                                <q-item-section avatar class="text-bold">
                                    ${{
                                        Math.round(
                                            week_commissions[week]["salary"] *
                                                100
                                        ) / 100
                                    }}
                                </q-item-section>
                            </q-item>
                            <template
                                v-for="commission in Object.keys(
                                    week_commissions[week]
                                )"
                                :key="`week-${week}-commission-${commission}`"
                            >
                                <q-item v-if="commission !== 'salary'">
                                    <q-list dense>
                                        <q-item
                                            style="
                                                padding-right: 0px !important;
                                                padding-top: 0px !important;
                                                padding-bottom: 0px !important;
                                            "
                                        >
                                            <q-item-section>
                                                <q-item-label>{{
                                                    fieldsRules[commission][
                                                        "label"
                                                    ]
                                                }}</q-item-label>
                                            </q-item-section>
                                            <q-item-section avatar>
                                                ${{
                                                    Math.round(
                                                        week_commissions[week][
                                                            commission
                                                        ]["salary"] * 100
                                                    ) / 100
                                                }}
                                            </q-item-section>
                                        </q-item>
                                    </q-list>
                                </q-item>
                            </template>
                        </q-list>
                    </q-item>
                </q-list>
            </div>
            <div v-if="monthly_commissions">
                <b>Bonos mensuales pagados</b>
                <q-list dense>
                    <q-item
                        v-for="year in Object.keys(monthly_commissions)"
                        :key="`year-${year}`"
                        style="padding: 0"
                    >
                        <q-list dense>
                            <template
                                v-for="month in Object.keys(
                                    monthly_commissions[year]
                                )"
                                :key="`year-${year}-month-${month}`"
                            >
                                <q-item>
                                    <q-item-section>
                                        {{ monthsLang[month] }}/{{ year }}
                                    </q-item-section>
                                    <q-item-section avatar>
                                        ${{
                                            monthly_commissions[year][month][
                                                "salary"
                                            ]
                                        }}
                                    </q-item-section>
                                </q-item>
                            </template>
                        </q-list>
                    </q-item>
                </q-list>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="save">Registar pago</button>
        </template>
    </modal>
</template>

<script setup>
import { ref, computed } from "vue";
import Modal from "../../../../shared/ModalSimple.vue";
import { fieldsRules, monthsLang } from "./helper/helper";
import moment from "moment";
const props = defineProps({
    data: Object,
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emits = defineEmits(["update:showModal", "save"]);
const user = computed(() => {
    let user = props.data?.seller.user ?? null;
    return user
        ? `${user.name} ${user.father_last_name} ${user.mother_last_name}`
        : "";
});

const week_commissions = computed(() => {
    return props.data?.week_commissions ?? null;
});

const monthly_commissions = computed(() => {
    return props.data?.monthly_commissions ?? null;
});

const updateShow = (newValue) => {
    emits("update:showModal", newValue);
};

const save = () => {
    emits("save");
};

const getDateFormat = (str) => {
    const temp = str.split(" - ");
    const from = moment(temp[0].substring(0, 10), "YYYY-MM-DD").format(
        "DD/MM/YYYY"
    );
    const to = moment(temp[1].substring(0, 10), "YYYY-MM-DD").format(
        "DD/MM/YYYY"
    );
    console.log(temp[0].substring(0, 9));

    return `${from} - ${to}`;
};
</script>
