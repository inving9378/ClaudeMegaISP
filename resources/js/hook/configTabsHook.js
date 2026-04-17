import {reactive} from "vue";
import ConfigTabs from "../helpers/ConfigTabs";

export const configTabsHook = reactive({
    data: new ConfigTabs({}),
});
