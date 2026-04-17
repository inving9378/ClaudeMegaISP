import axios from "axios";
import toastr from "toastr";

export const deleteRowDatatable = async (module, id, table) => {
    await axios["post"](`/${module}/destroy/${id}`,{module: module}).then((response) => {

        if(response.data.error){
            alert(response.data.error);
            return true;
        } else {
        toastr.success(`Elemento eliminado correctamente`, module)
        return true;
        }
    });
    return false;
}
