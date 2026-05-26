const getOnuStatusClass = (status) => {
    let cls = null;
    switch (status) {
        case "Online":
            cls = "positive";
            break;
        case "Offline":
            cls = "grey";
            break;
        case "Power fail":
            cls = "grey";
            break;
        case "LOS":
            cls = "negative";
            break;
        default:
            cls = "brown";
            break;
    }
    return cls;
};

const getOnuStatusIcon = (status) => {
    let cls = null;
    switch (status) {
        case "Online":
            cls = "mdi-earth";
            break;
        case "Offline":
            cls = "mdi-earth";
            break;
        case "Power fail":
            cls = "fa fa-plug";
            break;
        case "LOS":
            cls = "mdi-link-variant-off";
            break;
        default:
            break;
    }
    return cls;
};

export function useOlts() {
    return {
        getOnuStatusClass,
        getOnuStatusIcon,
    };
}
