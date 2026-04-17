<template>
    <div class="col-12 col-lg-6 col-lg-3" v-if="data.name">
        <span v-if="hasPermission.data.canView('client_edit_balance')"
            ><i
                class="fas fa-question cursor-pointer"
                @click="showModalEdit('modaleditUpdateBalance')"
            ></i
        ></span>
        <span class="customer-billing-balance-title"
            ><b class="customer-name-wrapper">{{ data.name }}</b>
            <span>| Account balance:</span>
            <b class="customer-balance" :key="data.balance"
                >$ {{ data.balance }}</b
            ></span
        >
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import { getClientWithBalance } from "../helpers/helper";
import { changeBalance } from "./comun_variable";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

export default {
    name: "ClientInfoAccountBalance",
    props: {
        client_id: String,
        authuserid: Number | String,
    },
    setup(props) {
        const data = reactive({
            balance: null,
            name: null,
        });

        const firstLoad = ref(true);
        const hasPermission = reactive({
            data: new Permission({}),
        });

        watch(changeBalance, async () => {
            if (!firstLoad.value) {
                if (changeBalance.value == true) {
                    await getClientWithBalance(props.client_id).then(
                        (response) => {
                            data.balance = response.balance;
                            data.name = response.name;
                        }
                    );
                }
                changeBalance.value = false;
            }
        });

        onMounted(async () => {
            hasPermission.data = new Permission(await allViewHasPermission());
            const response = await getClientWithBalance(props.client_id);
            data.balance = response.balance;
            data.name = response.name;
            firstLoad.value = false;
        });

        const showModalEdit = async (modal) => {
            $(`#${modal}`).modal("show");
        };

        return { data, showModalEdit, hasPermission };
    },
};
</script>

<style scoped></style>
