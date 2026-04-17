import Swal from "sweetalert2";
import { isFullScreen } from "../composables/useFullScreen";

export const message = (msg, icon = "success") => {
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        target: isFullScreen.value ? "#map" : "body",
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        },
    });
    Toast.fire({
        icon: icon,
        text: msg,
    });
};

export const errorValidation = () => {
    message("Rectifique los errores", "error");
};

export const error500 = () => {
    message(
        "Ha ocurrido un error al realizar esta operación. Consulte al administrador",
        "error"
    );
};
