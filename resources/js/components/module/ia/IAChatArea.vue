<template>
    <div class="ia-chat-area" ref="scrollEl">
        <div v-if="!mensajes.length && !loading" class="ia-empty-state">
            <i class="bi bi-chat-dots" style="font-size: 3em; opacity: 0.4"></i>
            <p class="mt-3">{{ emptyStateText }}</p>
        </div>

        <IAMessageBubble
            v-for="m in mensajes"
            :key="m.id"
            :mensaje="m"
        />

        <div v-if="loading" class="ia-message ia-assistant">
            <div class="ia-bubble">
                <span class="ia-typing">
                    <span></span><span></span><span></span>
                </span>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, watch, nextTick } from "vue";
import IAMessageBubble from "./IAMessageBubble.vue";

export default {
    name: "IAChatArea",
    components: { IAMessageBubble },
    props: {
        mensajes: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        emptyStateText: { type: String, default: "" },
    },
    setup(props) {
        const scrollEl = ref(null);

        const scrollAbajo = () => {
            nextTick(() => {
                if (scrollEl.value) {
                    scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
                }
            });
        };

        watch(() => props.mensajes.length, scrollAbajo);
        watch(() => props.loading, scrollAbajo);

        return { scrollEl };
    },
};
</script>

<style scoped>
.ia-chat-area {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #fafafa;
}
.ia-empty-state {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}
.ia-message {
    display: flex;
    margin-bottom: 12px;
}
.ia-message.ia-assistant { justify-content: flex-start; }
.ia-bubble {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
    background: #f3f4f6;
}
.ia-typing { display: inline-flex; gap: 4px; }
.ia-typing span {
    width: 6px; height: 6px; border-radius: 50%;
    background: #9ca3af;
    animation: ia-blink 1.2s infinite ease-in-out;
}
.ia-typing span:nth-child(2) { animation-delay: 0.2s; }
.ia-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes ia-blink {
    0%, 100% { opacity: 0.3; transform: translateY(0); }
    50% { opacity: 1; transform: translateY(-3px); }
}

/* Dark mode */
body[data-layout-mode="dark"] .ia-chat-area {
    background: #1f2227;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-bubble {
    background: #2d3036;
}
</style>
