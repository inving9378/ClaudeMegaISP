<template>
    <div class="col-12">
        <div class="card">
            <div class="card-body px-0">
                <div class="px-3" data-simplebar style="max-height: 352px;">
                    <ul class="list-unstyled activity-wid mb-0">

                        <li v-for="(item,key) in data"
                            :class="`activity-list ${data.length - 1 != key ? 'activity-border' : '' }`">
                            <div class="activity-icon avatar-md">
                                                        <span class="avatar-title bg-soft-warning text-warning rounded-circle">
                                                        <i class="bx bx-bitcoin font-size-24"></i>
                                                        </span>
                            </div>
                            <div class="timeline-list-item">
                                <div class="d-flex">
                                    <div class="flex-grow-1 overflow-hidden me-4">
                                        <h5 class="font-size-14 mb-1">{{ item.date }}</h5>
                                        <p class="text-truncate text-muted font-size-13">{{ item.text }}</p>
                                    </div>
                                    <div class="flex-shrink-0 text-end me-3">
                                        <h6 class="mb-1"><i class="fas fa-eye cursor-pointer" @click="showInfo(item)"></i></h6>
                                    </div>

                                    <div class="flex-shrink-0 text-end">

                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- end card body -->
        </div>
        <!-- end card -->
    </div>
</template>

<script>
import {getLogActivities} from "../helpers/helper";
import {onMounted, ref} from "vue";

export default {
    name: "CrmRecentActivity",
    props: {
        id: String
    },
    setup(props, {emit}){
        const data = ref([])

        onMounted(async () => {
            data.value = await getLogActivities(props.id, 'Crm');
        })

        const showInfo = (item) => {
            emit('show-information', item.data);
            $('.modal-center.modal-activity').modal('show');
        }
        return {data, showInfo}
    }
}
</script>

<style scoped>

</style>
