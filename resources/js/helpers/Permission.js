class Permission {
    /**
     * Create a new Permission instance.
     *
     * @param {object} data
     */
    constructor(data) {
        this.originalData = data
    }

    canDo(action){
        return this.originalData.hasOwnProperty(action) || this.originalData.hasOwnProperty('super-administrator');
    }

    canView(view){
        return this.originalData.hasOwnProperty(view) || this.originalData.hasOwnProperty('super-administrator');
    }
}

export default Permission;
