import { Loading } from "../../../public/plugins/quasar/js/quasar.umd.prod";
export const showLoading = (c = null) => {
    let config = {
        spinnerColor: "blue",
    };
    if (c !== null) {
        let text = "";
        config["html"] = true;
        config["boxClass"] = "bg-grey-2 text-grey-9";
        if (c == "showTextDef") {
            text = "<p>procesando , por favor espere...</p>";
        } else {
            text += `<p class="text-danger">${c}</p>`;
        }

        config["message"] = text;
    }
    Loading.show(config);
};

export const hideLoading = () => {
    Loading.hide();
};
