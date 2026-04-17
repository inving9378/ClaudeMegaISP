import { ref } from "vue";

export const isFullScreen = ref(false);

export const setFullScreen = (value) => {
    isFullScreen.value = value;
};

export function useFullScreen() {
    return {
        isFullScreen,
        setFullScreen,
    };
}
