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
    name: "LocationInformation",
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
        const elements = [
            {
                relation: 'router',
                value: 'title'
            }
        ];

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
                if (data[val.relation]) {
                    items.value[val.relation] = {
                        name: val.relation,
                        value: data[val.relation][val.value]
                    }
                }
            })
        })

        const initComponent = async (id) => {
            data.value = await getData(id, 'Location');
        };

        return {data, items};
    }
}
</script>

<style scoped>

</style>
