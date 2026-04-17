class ConfigTabs {
    constructor() {
        this.config = {};
    }

    get(reload = true, tabPanel) {
        if (reload) {
            return this.getFromDB(tabPanel);
        }
        return this.config;
    }

    split(elements) {
        let values = _.split(this.config, ".");
        if (values.length - 1 >= elements) {
            return values[elements];
        }
        return [];
    }

    async getFromDB(tabPanel) {
        await axios
            .post("/get-config-tabs", {
                tabPanel,
            })
            .then((r) => (this.config = r.data));
        return this.config;
    }

    async setNewConfig(tabPanel) {
        await axios.post("/set-config-tabs", tabPanel).then();
    }
}

export default ConfigTabs;
