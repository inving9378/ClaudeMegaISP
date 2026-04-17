import { ref, onMounted } from "vue";

export const activeTab = ref(
    localStorage.getItem("activeTab") || "#navs-pills-justified-information"
);

export const userId = ref(null);

export const setActiveTab = async (tab) => {
    activeTab.value = tab;
    localStorage.setItem("activeTab", tab);
};

export const setUserId = (id) => {
    userId.value = id;
};

export const getUserId = () => {
    return userId.value;
};
