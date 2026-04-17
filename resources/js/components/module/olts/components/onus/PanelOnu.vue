<template>
    <template v-if="!fromForm">
        <q-btn
            no-caps
            size="sm"
            color="primary"
            label="Ver"
            @click="showDialog = true"
            v-if="hasBtn"
        >
            <q-tooltip>{{
                hasPermission?.data.canView("onu_edit")
                    ? "Administrar onu"
                    : "Ver detalles"
            }}</q-tooltip>
        </q-btn>
        <q-item-label
            class="cursor-pointer text-primary"
            @click="showDialog = true"
            v-else
            >{{ onu?.sn }}
            <q-tooltip>{{
                hasPermission?.data.canView("onu_edit")
                    ? "Administrar onu"
                    : "Ver detalles"
            }}</q-tooltip>
        </q-item-label>
    </template>

    <q-dialog
        v-model="showDialog"
        persistent
        full-width
        @before-show="onBeforeShow"
        @hide="onHide"
    >
        <q-card style="width: 700px; max-width: 80vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Panel de la ONU</div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-item
                    class="bg-warning text-white q-ma-lg"
                    v-if="!onu.configured && !hideConfiguredMsg"
                >
                    <q-item-section
                        v-if="hasPermission?.data.canView('onu_edit')"
                    >
                        <p>
                            Esta ONU se guarda para su posterior autorización.
                            Finalice la autorización desde la pantalla
                            <b
                                class="text-primary cursor-pointer"
                                @click="onChangeUnconfigured"
                                >ONUs desconfiguradas</b
                            >
                            o seleccione manualmente una
                            <b
                                class="text-primary cursor-pointer"
                                @click="configureBoardAndPort = true"
                                >board</b
                            >
                            y un
                            <b
                                class="text-primary cursor-pointer"
                                @click="configureBoardAndPort = true"
                                >puerto</b
                            >.
                        </p>
                    </q-item-section>
                    <q-item-section v-else>
                        Esta ONU se guarda para su posterior autorización.
                        Finalice la autorización desde la pantalla Sin
                        configurar o seleccione manualmente una board y un
                        puerto.
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            round
                            flat
                            size="sm"
                            icon="close"
                            @click="hideConfiguredMsg = true"
                        />
                    </q-item-section>
                </q-item>
                <div class="row">
                    <div class="col">
                        <q-list dense>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                    >
                                        OLT &nbsp;
                                        <q-icon
                                            name="fas fa-share-square"
                                            class="text-primary cursor-pointer"
                                            @click="
                                                onFilter({
                                                    olt_id: onu.olt_id,
                                                })
                                            "
                                        />
                                    </q-item-label>
                                </q-item-section>
                                <q-item-section>
                                    <form-move-onu
                                        :object="onu"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        onu.olt_name
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Tarjeta &nbsp;
                                        <q-icon
                                            name="fas fa-share-square"
                                            class="text-primary cursor-pointer"
                                            @click="
                                                onFilter({
                                                    olt_id: onu.olt_id,
                                                    board: onu.board,
                                                })
                                            "
                                    /></q-item-label>
                                </q-item-section>
                                <q-item-section>
                                    <form-move-onu
                                        :object="onu"
                                        :show="configureBoardAndPort"
                                        field="board"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        @hide="configureBoardAndPort = false"
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        onu.board
                                    }}</q-item-label></q-item-section
                                >
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Puerto &nbsp;
                                        <q-icon
                                            name="fas fa-share-square"
                                            class="text-primary cursor-pointer"
                                            @click="
                                                onFilter({
                                                    olt_id: onu.olt_id,
                                                    board: onu.board,
                                                    port: onu.port,
                                                })
                                            "
                                    /></q-item-label>
                                </q-item-section>
                                <q-item-section
                                    ><form-move-onu
                                        :object="onu"
                                        field="port"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        onu.port
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >ONU</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section
                                    >{{ onu.onu_nomenclature }}
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Canal
                                        {{ onu.onu_pon_type }}</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section
                                    ><form-channel
                                        :onu="onu"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        onu.pon_type
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >SN</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section>{{ onu?.sn }} </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Tipo ONU &nbsp;
                                        <q-icon
                                            name="fas fa-share-square"
                                            class="text-primary cursor-pointer"
                                            @click="
                                                onFilter({
                                                    onu_type_id:
                                                        onu.onu_type_id,
                                                })
                                            "
                                    /></q-item-label>
                                </q-item-section>
                                <q-item-section
                                    ><form-onu-type
                                        :object="onu"
                                        field="onu_type_name"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        onu.onu_type_name
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Zona &nbsp;
                                        <q-icon
                                            name="fas fa-share-square"
                                            class="text-primary cursor-pointer"
                                            @click="
                                                onFilter({
                                                    zone_id: onu.zone_id,
                                                })
                                            "
                                    /></q-item-label>
                                </q-item-section>
                                <q-item-section
                                    ><form-change-location
                                        :object="onu"
                                        field="zone_name"
                                        :hasPermission="hasPermission"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                    />
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >ODB (Splitter)</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section>
                                    <form-change-location
                                        :object="onu"
                                        field="odb_name"
                                        :hasPermission="hasPermission"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                    />
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Nombre</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section>
                                    <form-change-location
                                        :object="onu"
                                        field="name"
                                        :hasPermission="hasPermission"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                /></q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Dirección/Comentario</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section
                                    ><form-change-location
                                        :object="onu"
                                        field="address"
                                        :hasPermission="hasPermission"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                    />
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Contacto</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section
                                    ><form-change-location
                                        :object="onu"
                                        field="contact"
                                        :hasPermission="hasPermission"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                    />
                                </q-item-section>
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >Fecha de autorización</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section>
                                    {{
                                        onu.authorization_date_humans
                                    }}</q-item-section
                                >
                            </q-item>
                            <q-item class="q-gutter-md">
                                <q-item-section avatar>
                                    <q-item-label
                                        class="olt-prop text-right text-bold"
                                        >ID externo de la ONU</q-item-label
                                    >
                                </q-item-section>
                                <q-item-section
                                    ><form-onu-external-id
                                        :object="onu"
                                        field="unique_external_id"
                                        @update="
                                            (data) => emits('update', data)
                                        "
                                        v-if="
                                            hasPermission?.data.canView(
                                                'onu_edit'
                                            )
                                        "
                                    />
                                    <q-item-label v-else>{{
                                        defaultValue(onu.unique_external_id)
                                    }}</q-item-label>
                                </q-item-section>
                            </q-item>
                        </q-list>
                    </div>
                    <div class="col">
                        <div class="column">
                            <image-component
                                :url="`/olts/onus/image/${onu.id}`"
                                max-width="350px"
                            />
                        </div>
                        <div class="column">
                            <q-list dense>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Estado</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section
                                        ><div class="row">
                                            <div class="col col-auto">
                                                {{ onu.status }}
                                            </div>
                                            <div
                                                class="col col-auto"
                                                style="padding: 0"
                                            >
                                                <q-icon
                                                    :name="onu.icon"
                                                    :color="onu.status_cls"
                                                    size="16px"
                                                />
                                            </div>
                                            <div
                                                class="col col-auto"
                                                v-if="loading.status"
                                            >
                                                <q-spinner-ios
                                                    color="primary"
                                                    size="xs"
                                                />
                                            </div>
                                            <div
                                                class="col col-auto"
                                                v-else-if="
                                                    !loading.status &&
                                                    onu.last_status_change
                                                "
                                            >
                                                ({{
                                                    onu.last_status_change_humans
                                                }})
                                            </div>
                                        </div>
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Señal Rx ONU/OLT</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section
                                        >{{ onu.onu_signal }}
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >VLANs adjuntas</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section
                                        ><form-attachments-vlans
                                            :object="onu"
                                            @update="
                                                (data) => emits('update', data)
                                            "
                                            v-if="
                                                hasPermission?.data.canView(
                                                    'onu_edit'
                                                )
                                            "
                                        />
                                        <q-item-label v-else>{{
                                            defaultValue(
                                                onu.service_ports
                                                    ?.map((s) => s.vlan)
                                                    .join(",")
                                            )
                                        }}</q-item-label>
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Modo ONU</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section
                                        ><form-update-mode
                                            :onu="onu"
                                            :has-permission="hasPermission"
                                            field="onu_mode"
                                            @update="
                                                (data) => emits('update', data)
                                            "
                                        />
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >TR069</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section>
                                        <form-mgmt-ip-and-vo-ip
                                            :onu="onu"
                                            :has-permission="hasPermission"
                                            :field="
                                                onu.tr069 === 'Disabled'
                                                    ? 'tr069'
                                                    : 'tr069_profile'
                                            "
                                            @update="
                                                (data) => emits('update', data)
                                            "
                                        />
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Mgmt IP</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section>
                                        <form-mgmt-ip-and-vo-ip
                                            :onu="onu"
                                            :has-permission="hasPermission"
                                            field="onu_mgmt_ip"
                                            @update="
                                                (data) => emits('update', data)
                                            "
                                        />
                                    </q-item-section>
                                </q-item>
                                <q-item class="q-gutter-md" v-if="onu.wan_mode">
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Modo config. WAN</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section>
                                        <form-update-mode
                                            :onu="onu"
                                            :has-permission="hasPermission"
                                            @update="
                                                (data) => emits('update', data)
                                            "
                                        />
                                    </q-item-section>
                                </q-item>
                                <template v-if="onu.wan_mode === 'PPPoE'">
                                    <q-item class="q-gutter-md">
                                        <q-item-section avatar>
                                            <q-item-label>
                                                <q-item-label
                                                    class="olt-prop text-right text-bold"
                                                    >Usuario PPPoE</q-item-label
                                                >
                                            </q-item-label>
                                        </q-item-section>
                                        <q-item-section>
                                            <password-label
                                                :password="onu.username"
                                            />
                                        </q-item-section>
                                    </q-item>
                                    <q-item class="q-gutter-md">
                                        <q-item-section avatar>
                                            <q-item-label>
                                                <q-item-label
                                                    class="olt-prop text-right text-bold"
                                                    >Contraseña
                                                    PPPoE</q-item-label
                                                >
                                            </q-item-label>
                                        </q-item-section>
                                        <q-item-section>
                                            <password-label
                                                :password="onu.password"
                                            />
                                        </q-item-section>
                                    </q-item>
                                </template>
                                <q-item
                                    class="q-gutter-md"
                                    v-if="onu.wan_mode === 'Static IP'"
                                >
                                    <q-item-section avatar>
                                        <q-item-label>
                                            <q-item-label
                                                class="olt-prop text-right text-bold"
                                                >Dirección IPv4</q-item-label
                                            >
                                        </q-item-label>
                                    </q-item-section>
                                    <q-item-section>
                                        <ip-label :ip="onu.ip_address" />
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </div>
                    </div>
                </div>
                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label class="olt-prop text-right text-bold"
                            >Estado</q-item-label
                        >
                    </q-item-section>
                    <q-item-section>
                        <status :onu="onu" />
                    </q-item-section>
                </q-item>
                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label class="olt-prop text-right text-bold">
                            Tráfico/Señal
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <image-component
                            :url="`/olts/onus/traffic-graph/${onu.id}`"
                        />
                    </q-item-section>
                    <q-item-section>
                        <image-component
                            :url="`/olts/onus/signal-graph/${onu.id}`"
                        />
                    </q-item-section>
                </q-item>

                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label
                            class="olt-prop text-right text-bold q-pt-md"
                        >
                            Perfiles de velocidad
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <speed-profile
                            :onu="onu"
                            :has-permission="hasPermission"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </q-item>

                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label
                            class="olt-prop text-right text-bold q-pt-md"
                        >
                            Puertos Ethernet
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <eth-ports
                            :onu="onu"
                            :has-permission="hasPermission"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </q-item>
                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label
                            class="olt-prop text-right text-bold q-pt-md"
                        >
                            Puertos WiFi
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <wifi-ports
                            :onu="onu"
                            :has-permission="hasPermission"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </q-item>
                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label class="olt-prop text-right text-bold">
                            Servicios VoIP
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <form-mgmt-ip-and-vo-ip
                            :onu="onu"
                            :has-permission="hasPermission"
                            field="voip_service"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </q-item>
                <q-item
                    dense
                    class="q-gutter-md q-pt-md"
                    v-if="onu.voip_service === 'Enabled'"
                >
                    <q-item-section avatar top>
                        <q-item-label
                            class="olt-prop text-right text-bold q-pt-md"
                        >
                            VoIP
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <voip-ports
                            :onu="onu"
                            :has-permission="hasPermission"
                            @update="(data) => emits('update', data)"
                        />
                    </q-item-section>
                </q-item>
                <q-item dense class="q-gutter-md q-pt-md">
                    <q-item-section avatar top>
                        <q-item-label class="olt-prop text-right text-bold">
                            CATV
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <form-catv :onu="onu" :has-permission="hasPermission" />
                    </q-item-section>
                </q-item>
                <q-item
                    dense
                    class="q-gutter-md q-pt-md"
                    v-if="onu.tr069 === 'Enabled'"
                >
                    <q-item-section avatar top>
                        <q-item-label class="olt-prop text-right text-bold">
                            Contraseña de usuario web
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <form-web-password
                            :onu="onu"
                            :has-permission="hasPermission"
                        />
                    </q-item-section>
                </q-item>
                <q-item dense class="q-gutter-md">
                    <q-item-section avatar>
                        <q-item-label class="olt-prop text-right text-bold">
                        </q-item-label>
                    </q-item-section>
                    <q-item-section>
                        <div class="q-gutter-xs">
                            <template v-if="onu.configured">
                                <reboot-onu
                                    :onu="onu"
                                    v-if="
                                        hasPermission?.data.canView(
                                            'onu_reboot'
                                        )
                                    "
                                />
                                <resync-onu
                                    :onu="onu"
                                    v-if="
                                        hasPermission?.data.canView(
                                            'onu_resync'
                                        )
                                    "
                                />
                                <default-onu
                                    :onu="onu"
                                    v-if="
                                        hasPermission?.data.canView(
                                            'onu_default'
                                        )
                                    "
                                />
                                <enable-onu
                                    :onu="onu"
                                    @enabled="(val) => emits('enabled', val)"
                                    v-if="
                                        hasPermission?.data.canView(
                                            'onu_enable_disable'
                                        )
                                    "
                                />
                            </template>
                            <remove-onu
                                :id="onu.id"
                                @removed="
                                    () => {
                                        emits('removed');
                                        showDialog = false;
                                    }
                                "
                                v-if="hasPermission?.data.canView('onu_remove')"
                            />
                        </div>
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import ImageComponent from "./ImageComponent.vue";
import SpeedProfile from "./SpeedProfile.vue";
import EthPorts from "./EthPorts.vue";
import WifiPorts from "./WifiPorts.vue";
import VoipPorts from "./VoipPorts.vue";
import RemoveOnu from "./RemoveOnu.vue";
import EnableOnu from "./EnableOnu.vue";
import RebootOnu from "./RebootOnu.vue";
import ResyncOnu from "./ResyncOnu.vue";
import DefaultOnu from "./DefaultOnu.vue";

import FormMoveOnu from "../form/FormMoveOnu.vue";
import FormChangeLocation from "../form/FormChangeLocation.vue";
import FormOnuExternalId from "../form/FormOnuExternalId.vue";
import FormOnuType from "../form/FormOnuType.vue";
import FormAttachmentsVlans from "../form/FormAttachmentsVlans.vue";
import FormMgmtIpAndVoIp from "../form/FormMgmtIpAndVoIp.vue";
import FormChannel from "../form/FormChannel.vue";
import FormUpdateMode from "../form/FormUpdateMode.vue";
import FormWebPassword from "../form/FormWebPassword.vue";
import FormCatv from "../form/FormCatv.vue";

import PasswordLabel from "../form/PasswordLabel.vue";
import IpLabel from "../form/IpLabel.vue";
import Status from "./Status.vue";

import { useUtils } from "../../../../../composables/useUtils";
import { getOLTData } from "../../helper/request";
import { setActiveTab } from "../../../../../hook/appConfig";

defineOptions({
    name: "PanelOnu",
});

const props = defineProps({
    onu: Object,
    show: Boolean,
    hasBtn: {
        type: Boolean,
        default: true,
    },
    fromForm: {
        type: Boolean,
        default: false,
    },
    fromClient: Boolean,
    hasPermission: Object,
});

const emits = defineEmits(["removed", "hide", "enabled", "filter", "update"]);

const { defaultValue } = useUtils();

const showDialog = ref(false);
const hideConfiguredMsg = ref(false);
const configureBoardAndPort = ref(false);

const loading = ref({
    status: false,
});

let timer = null;

watch(
    () => props.show,
    (n) => {
        if (n) {
            showDialog.value = n;
        }
    }
);

const onFilter = (params) => {
    emits("filter", params);
    showDialog.value = false;
};

const onBeforeShow = async () => {
    loading.value = {
        status: true,
    };
    syncOnu();
    getSignalsAndStatus();
    if (!timer) {
        timer = setInterval(() => {
            getSignalsAndStatus();
        }, 30000);
    }
};

const onHide = () => {
    clearInterval(timer);
    emits("hide");
};

const updateLoadig = (name, load) => {
    loading.value[name] = load;
};

const getSignalsAndStatus = async () => {
    updateLoadig("status", true);
    const result = await getOLTData(
        `/olts/onus/get-signal-and-status/${props.onu.id}`
    );
    updateLoadig("status", false);
    if (result && result.success) {
        let { id, signal_1310, signal_1490, signal, status } = result.onu;
        emits("update", {
            id,
            signal_1310,
            signal_1490,
            signal,
            status,
        });
    }
};

const syncOnu = async () => {
    const result = await getOLTData(`/olts/onus/sync/${props.onu.id}`);
    if (result && result.success) {
        emits("update", result.onu);
    }
};

const onChangeUnconfigured = () => {
    setActiveTab("tab-olt-panel", "unconfigured");
    if (props.fromClient) {
        location.href = "/olts";
    } else {
        location.reload();
    }
};
</script>
<style scoped>
.olt-prop {
    min-width: 180px !important;
}
</style>
