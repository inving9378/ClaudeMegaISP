
import { resetDatatable } from "./filters.js";

class DatatableHelper {
    constructor(objt) {
        this.table = objt
    }

    reload(){
        resetDatatable.value = true;
    }
}

export default DatatableHelper;
