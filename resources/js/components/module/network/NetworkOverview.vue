<template>
    <div class="row mt-3" style="max-height: 1000px; overflow-y: scroll">
        <IpCard
            v-for="val in fieldsJson"
            :endNumber="getlastnum(val.ip)"
            :val="val"
        ></IpCard>
    </div>

    <div class="mt-2 text-end">
        <a
            href="javascript:void(0)"
            class="btn btn-primary btn-sm"
            @click="loadMore"
            :disabled="disabled"
            >Ver mas <i class="mdi mdi-arrow-right ms-1"></i
        ></a>
    </div>

    <h6 class="text-center border-top border-bottom p-3 mt-3">Leyenda</h6>
    <div class="row alert mt-3">
        <LegendCards> </LegendCards>
    </div>
</template>

<script>
import IpCard from "./ip/IpCard";
import { onMounted, reactive, ref } from "vue";
import Form from "../../../helpers/Form";
import LegendCards from "./ip/LegendCards";

export default {
    name: "NetworkOverview",
    components: { IpCard, LegendCards },
    props: {
        id: {
            type: String,
            default: null,
        },
    },
    setup(props) {
        const page = ref(1);
        const dataForm = reactive({
            data: new Form({ page: 1 }),
        });
        const ipCard = reactive({
            data: [],
        });
        const fieldsJson = ref(ipCard.data);
        const disabled = ref(false);

        if (props.id) {
            onMounted(async () => {
                disabled.value = true;
                await dataForm.data
                    .post(`/red/ipv4/network/${props.id}`)
                    .then((response) => {
                        _.forEach(response, (r) => ipCard.data.push(r));
                        disabled.value = false;
                    });
            });
        }

        const loadMore = async () => {
            page.value++;
            dataForm.data["page"] = page.value;
            disabled.value = true;
            await dataForm.data
                .post(`/red/ipv4/network/${props.id}`)
                .then((response) => {
                    _.forEach(response, (r) => ipCard.data.push(r));
                    disabled.value = false;
                });
        };

        const getlastnum = (value) => {
            return _.last(_.split(value, ".", 4));
        };
        return { fieldsJson, dataForm, getlastnum, loadMore, ipCard, disabled };
    },
};
</script>

<style scoped></style>
