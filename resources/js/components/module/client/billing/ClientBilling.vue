<template>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="p-3">
                    <q-tabs
                        v-model="currentTab"
                        no-caps
                        dense
                        active-color="indigo-6"
                        @update:model-value="onChangeTab"
                    >
                        <q-tab
                            name="view"
                            label="Vista de Facturacion"
                            style="width: 25%"
                        />
                        <q-tab
                            name="transactions"
                            label="Transacciones"
                            style="width: 25%"
                        />
                        <q-tab
                            name="facture"
                            label="Facturas"
                            style="width: 25%"
                        />
                        <q-tab
                            name="payment"
                            label="Pagos"
                            style="width: 25%"
                        />
                    </q-tabs>
                    <q-tab-panels
                        v-model="currentTab"
                        animated
                        :dark="darkMode"
                    >
                        <q-tab-panel name="view">
                            <ViewClientBilling
                                :id="id"
                                :typeOfBilling="typeOfBilling"
                                :authuserid="authuserid"
                            />
                        </q-tab-panel>

                        <q-tab-panel name="transactions">
                            <ViewClientTransaction
                                :id="id"
                                :editModal="editModal"
                            />
                        </q-tab-panel>

                        <q-tab-panel name="facture">
                            <ClientInvoice :id="id" />
                        </q-tab-panel>

                        <q-tab-panel name="payment">
                            <ViewClientPayment
                                :id="id"
                                :editModal="editModal"
                            />
                        </q-tab-panel>
                    </q-tab-panels>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import ViewClientBilling from "./ViewClientBilling";
import ViewClientPayment from "./payment/ViewClientPayment";
import ClientInvoice from "./invoice/ClientInvoice";
import ViewClientTransaction from "./transaction/ViewClientTransaction";
import { configTabsHook } from "../../../../hook/configTabsHook";
import { darkMode } from "../../../../hook/appConfig";

export default {
    name: "ClientBilling",
    props: {
        id: {
            type: String,
            default: null,
        },
        typeOfBilling: String,
        editModal: Object,
        authuserid: Number | String,
    },
    components: {
        ViewClientPayment,
        ViewClientBilling,
        ViewClientTransaction,
        ClientInvoice,
    },
    setup() {
        const currentTab = ref(null);

        onMounted(() => {
            setTab();
        });

        const setTab = async () => {
            let tab = await configTabsHook.data.getFromDB("facture");
            const cleanSavedTab = tab.trim();
            currentTab.value = cleanSavedTab ? cleanSavedTab : "view";
        };

        const onChangeTab = (tab) => {
            configTabsHook.data.setNewConfig({
                tabs: "facture",
                active: tab,
            });
        };

        return {
            currentTab,
            onChangeTab,
            darkMode,
        };
    },
};
</script>
