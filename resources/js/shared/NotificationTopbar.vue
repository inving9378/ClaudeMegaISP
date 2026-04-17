<template>
    <q-card flat v-if="Object.keys(data).length > 0">
        <q-item>
            <q-item-section>
                <q-item-label class="text-bold">Notificaciones</q-item-label>
            </q-item-section>
            <q-item-section avatar>
                Pendiente ({{ Object.keys(data).length }})
            </q-item-section>
        </q-item>
        <q-separator />
        <q-card-section style="max-height: 50vh" class="scroll q-pa-none">
            <q-list>
                <q-item
                    v-for="n in data"
                    :key="`notification-${n.id}`"
                    class="notification-item"
                    clickable
                    @click="onClick(n)"
                >
                    <q-item-section avatar top>
                        <img
                            src="/assets/images/users/avatar-3.jpg"
                            class="rounded-circle avatar-sm"
                            alt="user-pic"
                        />
                    </q-item-section>
                    <q-item-section class="q-mx-md">
                        <q-item-label class="text-bold">
                            {{ user(n) ?? "Sistema MegaNet" }}
                        </q-item-label>
                        <q-item-label class="q-pb-xs">
                            {{ text(n) }}
                        </q-item-label>
                        <q-item-label caption>
                            <i class="mdi mdi-clock-outline"></i>
                            <span>{{ n.created_at }}</span>
                        </q-item-label>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            round
                            color="primary"
                            size="xs"
                            flat
                            icon="fa fa-eye-slash"
                            :href="`/read-notification/${n.id}`"
                        >
                            <q-tooltip> Marcar como leída </q-tooltip>
                        </q-btn>
                    </q-item-section>
                </q-item>
            </q-list>
        </q-card-section>
        <q-separator />
        <q-card-actions class="no-gutter-x q-pa-xs">
            <q-btn
                no-caps
                label="Marcar todo como leído"
                color="primary"
                style="width: 100% !important"
                href="/read-all-notifications"
            />
        </q-card-actions>
    </q-card>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";

defineOptions({
    name: "NotificationTopbar",
});

const props = defineProps({
    notifications: String,
});

const data = ref([]);

onMounted(() => {
    data.value = JSON.parse(props.notifications);
});

const user = (data) => {
    return _.isEmpty(data.reported_by)
        ? _.isEmpty(data.model.created_by)
            ? null
            : data.model.created_by
        : data.reported_by;
};

const text = (data) => {
    return _.isEmpty(data.topic) ? data.model.title : data.topic;
};

const onClick = (n) => {
    if (n.type !== "App\\Notifications\\StandardNotification") {
        location.href = `/tickets/notifica/${n.id}`;
    } else if (!_.isEmpty(n.model.base_url)) {
        location.href = `${n.model.base_url}${n.id}`;
    }
};
</script>
<style scoped>
.notification-iten > .q-focus-helper {
    width: auto !important;
}
</style>
