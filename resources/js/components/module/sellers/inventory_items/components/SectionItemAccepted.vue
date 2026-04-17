<template>
    <q-list bordered class="rounded-borders">
        <q-item>
            <q-item-section top class="col-2 gt-sm">
                <q-item-label class="q-mt-sm">{{
                    item.name_item
                }}</q-item-label>
            </q-item-section>

            <q-item-section>
                <q-item-label lines="1">
                    <span class="text-weight-medium">{{ item.current_stock }}</span>
                </q-item-label>
            </q-item-section>

            <q-item-section top side>
                <div class="text-grey-8 q-gutter-xs">

                </div>
            </q-item-section>
        </q-item>
    </q-list>
</template>

<script>
import Swal from "sweetalert2";
export default {
    name: "SectionItemAccepted",
    components: {},
    props: {
        item: Object,
    },
    emits: [""],
    setup(props) {


        return {

        };
    },
};
</script>

<style scoped></style>
