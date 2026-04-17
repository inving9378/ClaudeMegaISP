<template>
    <Accordion
        v-if="id"
        :items="items"
    ></Accordion>
</template>

<script>
import {onMounted, ref, watch} from "vue";
import {getData} from "../../../../helpers/Request";
import Accordion from "../../../base/shared/accordion/Accordion"

export default {
    name: "PartnerInformation",
    props: {
        id: {
            type: String,
            default: null,
        }
    },
    components: {Accordion},
    setup(props) {
        const data = ref();
        const items = ref({});
        const elements = ['internet', 'voz', 'router'];

        onMounted(() => {
            initComponent(props.id);
        });

        watch(
            () => props.id,
            (id, idBefore) => {
                initComponent(id);
            }
        );

        watch(data, (data, prevData) => {
            _.forEach(elements, (val) => {
                if (data[val].length) {
                    items.value[val] = {
                        name: val,
                        value: _.join(_.map(data[val], 'title'), ', ')
                    }
                }
            })
        })

        const initComponent = async (id) => {
            data.value = await getData(id, 'Partner');
        };

        return {data, items};
    }
}
</script>

<style scoped>

</style>
